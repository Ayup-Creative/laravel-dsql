<?php

namespace AyupCreative\AdvancedSearch\Compiler;

use AyupCreative\AdvancedSearch\AST\AggregateNode;
use AyupCreative\AdvancedSearch\AST\ArithmeticNode;
use AyupCreative\AdvancedSearch\AST\CastNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\LiteralNode;
use AyupCreative\AdvancedSearch\AST\Node;
use AyupCreative\AdvancedSearch\AST\QueryNode;
use AyupCreative\AdvancedSearch\Concerns\ResolvesRelationships;
use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Exceptions\LexerException;
use AyupCreative\AdvancedSearch\Exceptions\ParserException;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Compiles scalar SQL values, never PHP-side selection placeholders. */
class ExpressionCompiler
{
    use ResolvesRelationships;

    public function __construct(protected ColumnRegistry $columns) {}

    public function parseExpression(string $expression): ?Node
    {
        // Registered expressions accept native SQL as well as bracketed DSQL.
        // Bare SQL names/functions must not turn into DSQL string literals/functions.
        $trimmed = trim($expression);
        $quoted = (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'")) ||
            (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'));
        if (! str_contains($expression, '[') && ! $quoted && preg_match('/[A-Za-z_]/', $expression)) {
            return null;
        }
        try {
            $ast = (new PrattParser((new Lexer($expression))->tokenize()))->parse();
        } catch (LexerException|ParserException) {
            // Registered expressions may intentionally contain trusted native SQL.
            return null;
        }

        return $ast instanceof QueryNode ? $ast->criteria : null;
    }

    public function compile(Builder $query, Node $node, array $context = [], array $visited = []): CompiledExpression
    {
        if ($node instanceof CastNode) {
            return $this->compile($query, $node->expression, $context, $visited);
        }
        if ($node instanceof LiteralNode) {
            return new CompiledExpression('?', [$node->value]);
        }
        if ($node instanceof ArithmeticNode) {
            $left = $this->compile($query, $node->left, $context, $visited);
            $right = $this->compile($query, $node->right, $context, $visited);

            return new CompiledExpression("({$left->sql} {$node->operator} {$right->sql})", [...$left->bindings, ...$right->bindings]);
        }
        if ($node instanceof AggregateNode) {
            if (! $node->expression instanceof ColumnNode || ! in_array($node->function, ['COUNT', 'EXISTS'], true)) {
                throw new AdvancedSearchException('Aggregates require a relationship path.');
            }

            return $this->aggregate($query, explode('.', $node->expression->name), $node->function);
        }
        if (! $node instanceof ColumnNode) {
            throw new AdvancedSearchException('Unsupported SQL expression.');
        }

        $name = $node->name;
        $key = $query->getModel()::class.':'.$name;
        if (in_array($key, $visited, true)) {
            throw new AdvancedSearchException("Circular column or alias: {$name}");
        }
        $visited[] = $key;
        if (isset($context[$name])) {
            return $this->compile($query, $context[$name], $context, $visited);
        }

        $this->columns->registerFromClass($query->getModel()::class);
        $expression = $this->columns->resolveExpression($name, $query->getModel()::class);
        if ($expression !== null) {
            $parsed = $this->parseExpression($expression);

            return $parsed ? $this->compile($query, $parsed, $context, $visited) : new CompiledExpression($expression);
        }

        $table = $query->getModel()->getTable();
        $column = str_starts_with($name, $table.'.') ? substr($name, strlen($table) + 1) : $name;
        if (! str_contains($column, '.')) {
            $schemaTable = str_starts_with($table, 'laravel_reserved_') ? (new ($query->getModel()::class))->getTable() : $table;
            if (! $query->getModel()->getConnection()->getSchemaBuilder()->hasColumn($schemaTable, $column)) {
                throw new AdvancedSearchException("Unknown SQL column or expression: {$name}");
            }

            return new CompiledExpression($query->getQuery()->getGrammar()->wrap($query->getModel()->qualifyColumn($column)));
        }

        [$first, $remaining] = explode('.', $name, 2);
        $relation = $this->resolveRelationship($query->getModel(), $first);
        if (! $relation instanceof BelongsTo && ! $relation instanceof HasOne && ! $relation instanceof MorphOne) {
            throw new AdvancedSearchException("A plural relationship requires an explicit aggregate in scalar expressions: {$name}");
        }
        $subQuery = $relation->getRelationExistenceQuery($relation->getQuery(), $query);
        $value = $this->compile($subQuery, new ColumnNode($remaining), [], $visited);
        $subQuery->select([])->selectRaw($value->sql, $value->bindings)->limit(1);
        $base = $subQuery->toBase();

        return new CompiledExpression('('.$base->toSql().')', $base->getBindings());
    }

    protected function aggregate(Builder $query, array $parts, string $function): CompiledExpression
    {
        $relation = $this->resolveRelationship($query->getModel(), array_shift($parts));
        $subQuery = $relation->getRelationExistenceQuery($relation->getQuery(), $query);
        $subQuery->select([])->reorder();

        if ($parts !== []) {
            $inner = $this->aggregate($subQuery, $parts, $function);
            if ($function === 'COUNT') {
                $subQuery->selectRaw('COALESCE(SUM('.$inner->sql.'), 0)', $inner->bindings);
            } else {
                $subQuery->selectRaw('1')->whereRaw($inner->sql, $inner->bindings);
            }
        } else {
            $subQuery->selectRaw($function === 'COUNT' ? 'COUNT(*)' : '1');
        }
        $base = $subQuery->toBase();
        $sql = $function === 'COUNT' ? '('.$base->toSql().')' : 'EXISTS('.$base->toSql().')';

        return new CompiledExpression($sql, $base->getBindings());
    }
}
