<?php

namespace AyupCreative\AdvancedSearch\Compiler;

use AyupCreative\AdvancedSearch\AST\AggregateNode;
use AyupCreative\AdvancedSearch\AST\ArithmeticNode;
use AyupCreative\AdvancedSearch\AST\CastNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\ConditionNode;
use AyupCreative\AdvancedSearch\AST\DynamicValueNode;
use AyupCreative\AdvancedSearch\AST\LiteralNode;
use AyupCreative\AdvancedSearch\AST\LogicalNode;
use AyupCreative\AdvancedSearch\AST\Node;
use AyupCreative\AdvancedSearch\AST\QueryNode;
use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use AyupCreative\AdvancedSearch\Registry\DynamicValueRegistry;
use AyupCreative\AdvancedSearch\Registry\OperatorRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\Schema;

class QueryCompiler
{
    public function __construct(
        protected ColumnRegistry $columnRegistry,
        protected OperatorRegistry $operatorRegistry,
        protected DynamicValueRegistry $dynamicValueRegistry
    ) {}

    public function validate(Node $node, string $modelClass, array $context = []): void
    {
        if ($node instanceof QueryNode) {
            $fields = $node->fields;

            if (empty($fields) && $this->columnRegistry->hasDefaultSelections($modelClass)) {
                foreach ($this->columnRegistry->getDefaultSelections($modelClass) as $default) {
                    $fields[] = [
                        'expression' => new ColumnNode($default['name']),
                        'alias' => $default['is_alias'] ? $default['name'] : null,
                    ];
                }
            }

            $localContext = $context;
            foreach ($fields as $field) {
                $localBindings = [];
                $this->compileExpression($field['expression'], $modelClass, $localBindings, $localContext);

                $alias = $field['alias'];
                if (! $alias && $field['expression'] instanceof ColumnNode) {
                    $alias = $field['expression']->name;
                }

                if ($alias) {
                    $isSimpleMapping = $field['expression'] instanceof ColumnNode && $field['expression']->name === $alias;
                    if (! $isSimpleMapping) {
                        $localContext[$alias] = $field['expression'];
                    }
                }
            }

            if ($node->criteria) {
                $this->validate($node->criteria, $modelClass, $localContext);
            }

            return;
        }

        if ($node instanceof AggregateNode) {
            $this->validate($node->expression, $modelClass, $context);

            return;
        }

        if ($node instanceof LogicalNode) {
            $this->validate($node->left, $modelClass, $context);
            $this->validate($node->right, $modelClass, $context);

            return;
        }

        if ($node instanceof ConditionNode) {
            // Validate operator
            $this->operatorRegistry->resolve($node->operator);

            // Validate value (including dynamic values)
            $this->resolveValue($node->value);

            // Validate column/expression
            $localBindings = [];
            $this->compileExpression($node->column, $modelClass, $localBindings, $context);

            return;
        }
    }

    public function compile(Builder $query, Node $node, array $context = [])
    {
        if ($node instanceof QueryNode) {
            $modelClass = get_class($query->getModel());
            $fields = $node->fields;

            if (empty($fields) && $this->columnRegistry->hasDefaultSelections($modelClass)) {
                foreach ($this->columnRegistry->getDefaultSelections($modelClass) as $default) {
                    $fields[] = [
                        'expression' => new ColumnNode($default['name']),
                        'alias' => $default['is_alias'] ? $default['name'] : null,
                    ];
                }
            }

            if (! empty($fields)) {
                $selects = [];
                $bindings = [];
                $localContext = [];
                foreach ($fields as $field) {
                    $localBindings = [];
                    $sql = $this->compileExpression($field['expression'], $modelClass, $localBindings, $localContext, $query);

                    $alias = $field['alias'];
                    if (! $alias) {
                        $innerExpression = $field['expression'];
                        while ($innerExpression instanceof CastNode) {
                            $innerExpression = $innerExpression->expression;
                        }

                        if ($innerExpression instanceof ColumnNode) {
                            $alias = $innerExpression->name;
                        } else {
                            $alias = (string) $field['expression'];
                        }
                    }

                    if ($alias) {
                        $isSimpleMapping = $field['expression'] instanceof ColumnNode && $field['expression']->name === $alias;
                        if (! $isSimpleMapping) {
                            $localContext[$alias] = $field['expression'];
                        }

                        // Quote alias safely across drivers. If alias is a simple identifier, leave unquoted for readability.
                        $grammar = $query->getQuery()->getGrammar();
                        $isSimpleIdentifier = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) === 1;
                        if ($isSimpleIdentifier) {
                            $sql .= " AS $alias";
                        } else {
                            // Choose identifier wrapper based on grammar (MySQL uses backticks, others typically use double quotes)
                            $wrapper = ($grammar instanceof MySqlGrammar) ? '`' : '"';
                            // Treat the alias as a single identifier (do not split on dots)
                            $escaped = str_replace($wrapper, $wrapper.$wrapper, $alias);
                            $sql .= " AS {$wrapper}{$escaped}{$wrapper}";
                        }
                    }
                    $selects[] = $sql;
                    $bindings = array_merge($bindings, $localBindings);
                }
                $query->selectRaw(implode(', ', $selects), $bindings);
            }

            if ($node->criteria) {
                $this->compile($query, $node->criteria, $localContext ?? []);
            }

            foreach ($node->sorts as $sort) {
                // For sorting, we might also need to resolve the column name
                // But for now let's just use it directly if it's a simple column
                $query->orderBy($sort['column'], $sort['direction']);
            }

            if ($node->limit) {
                $query->limit($node->limit);
            }

            return;
        }

        if ($node instanceof LogicalNode) {
            $boolean = strtolower($node->boolean);
            if ($boolean === 'not') {
                $query->whereNot(function ($q) use ($node, $context) {
                    $this->compile($q, $node->left, $context);
                });

                return;
            }

            $method = $boolean === 'or' ? 'orWhere' : 'where';

            $query->$method(function ($q) use ($node, $context) {
                $this->compile($q, $node->left, $context);
                $this->compile($q, $node->right, $context);
            });

            return;
        }

        if ($node instanceof ConditionNode) {
            $modelClass = get_class($query->getModel());
            $columnResolver = $this->getResolver($query, $node->column, $modelClass, $context);
            $operator = $this->operatorRegistry->resolve($node->operator);

            $resolvedValue = $this->resolveValue($node->value);

            $operator->apply($query, $columnResolver, $resolvedValue);

            return;
        }

        if ($node instanceof AggregateNode) {
            if ($node->function === 'EXISTS' && $node->expression instanceof ColumnNode) {
                $query->has($node->expression->name);
            } elseif ($node->function === 'COUNT' && $node->expression instanceof ColumnNode) {
                $query->has($node->expression->name, '>=', 1);
            }

            return;
        }
    }

    protected function resolveValue($value): mixed
    {
        if (is_array($value) && isset($value['type'])) {
            if ($value['type'] === 'dynamic') {
                return [
                    'type' => 'scalar',
                    'value' => $this->resolveDynamicNode($value['value']),
                ];
            }

            if ($value['type'] === 'list') {
                return [
                    'type' => 'list',
                    'value' => array_map(fn ($v) => $this->resolveAndUnwrapValue($v), $value['value']),
                ];
            }
        }

        if ($value instanceof DynamicValueNode) {
            return [
                'type' => 'scalar',
                'value' => $this->resolveDynamicNode($value),
            ];
        }

        return $value;
    }

    protected function resolveDynamicNode(DynamicValueNode $node): mixed
    {
        $args = array_map(fn ($arg) => $this->resolveAndUnwrapValue($arg), $node->arguments);
        $result = $this->dynamicValueRegistry->resolve($node->name, $args);

        if ($node->next) {
            return $this->resolveDynamicChain($result, $node->next);
        }

        return $result;
    }

    protected function resolveDynamicChain($object, DynamicValueNode $node): mixed
    {
        $args = array_map(fn ($arg) => $this->resolveAndUnwrapValue($arg), $node->arguments);

        // We use method_exists and call_user_func for safety, but we are effectively allowing method calls.
        // Since we are only allowing these on objects returned by registered dynamic functions, it's safer than eval.
        if (! is_object($object)) {
            throw new AdvancedSearchException('Cannot call method on non-object');
        }

        if (! method_exists($object, $node->name) && ! method_exists($object, '__call')) {
            throw new AdvancedSearchException("Method {$node->name} does not exist on ".get_class($object));
        }

        $result = $object->{$node->name}(...$args);

        if ($node->next) {
            return $this->resolveDynamicChain($result, $node->next);
        }

        return $result;
    }

    protected function resolveAndUnwrapValue($value): mixed
    {
        $resolved = $this->resolveValue($value);

        return is_array($resolved) && isset($resolved['value']) ? $resolved['value'] : $resolved;
    }

    protected function getResolver(Builder $query, string|Node $column, string $modelClass, array $context = []): callable
    {
        $resolved = null;
        try {
            if (is_string($column)) {
                if (isset($context[$column])) {
                    return $this->getResolver($query, $context[$column], $modelClass, $context);
                }
                $resolved = $this->columnRegistry->resolve($column, $modelClass);
            } elseif ($column instanceof ColumnNode) {
                if (isset($context[$column->name])) {
                    return $this->getResolver($query, $context[$column->name], $modelClass, $context);
                }
                $resolved = $this->columnRegistry->resolve($column->name, $modelClass);
            } elseif ($column instanceof AggregateNode) {
                return function (Builder $q, string $op, $val) use ($column) {
                    if ($column->expression instanceof ColumnNode) {
                        $relation = $column->expression->name;
                        if ($column->function === 'COUNT') {
                            $q->has($relation, $op, $val);
                        } elseif ($column->function === 'EXISTS') {
                            if (($op === '=' || $op === 'equals') && $val === false) {
                                $q->doesntHave($relation);
                            } else {
                                $q->has($relation);
                            }
                        }
                    }
                };
            }
        } catch (AdvancedSearchException $e) {
            // Not in registry, fallback to raw column
        }

        if ($resolved) {
            if (is_callable($resolved)) {
                return $resolved;
            }

            if (is_string($resolved)) {
                $lexer = new Lexer($resolved);
                $parser = new PrattParser($lexer->tokenize());
                $ast = $parser->parse();

                if ($ast instanceof QueryNode) {
                    return $this->getResolver($query, $ast->criteria, $modelClass, $context);
                }
            }
        }

        return function (Builder $q, string $op, $val) use ($column, $modelClass, $context) {
            $bindings = [];
            $sql = $this->compileExpression($column, $modelClass, $bindings, $context);

            if (str_contains($sql, '.') && ! str_contains($sql, '(')) {
                $parts = explode('.', $sql);
                $actualColumn = array_pop($parts);
                $relation = implode('.', $parts);

                $q->whereHas($relation, function ($subQuery) use ($actualColumn, $op, $val) {
                    if ($op === 'in') {
                        $subQuery->whereIn($actualColumn, (array) $val);
                    } elseif ($op === 'between') {
                        $subQuery->whereBetween($actualColumn, (array) $val);
                    } else {
                        $subQuery->where($actualColumn, $op, $val);
                    }
                });

                return;
            }

            if ($op === 'in') {
                $placeholders = implode(',', array_fill(0, count($val), '?'));
                $q->whereRaw("$sql IN ($placeholders)", array_merge($bindings, (array) $val));
            } elseif ($op === 'between') {
                $q->whereRaw("$sql BETWEEN ? AND ?", array_merge($bindings, (array) $val));
            } elseif (str_ends_with($op, '_column')) {
                $actualOp = substr($op, 0, -7);
                $q->whereRaw("$sql $actualOp $val", $bindings);
            } else {
                $q->whereRaw("$sql $op ?", array_merge($bindings, [$val]));
            }
        };
    }

    protected function compileExpression(Node $node, string $modelClass, array &$bindings, array $context = [], ?Builder $query = null, array $visited = []): string
    {
        if ($node instanceof ColumnNode) {
            if (isset($context[$node->name])) {
                if (in_array($node->name, $visited)) {
                    return $node->name;
                }

                $visited[] = $node->name;

                return $this->compileExpression($context[$node->name], $modelClass, $bindings, $context, $query, $visited);
            }

            $resolved = $this->columnRegistry->resolveExpression($node->name, $modelClass);

            if ($resolved) {
                try {
                    $lexer = new Lexer($resolved);
                    $parser = new PrattParser($lexer->tokenize());
                    $ast = $parser->parse();

                    if ($ast instanceof QueryNode && $ast->criteria) {
                        return $this->compileExpression($ast->criteria, $modelClass, $bindings, $context, $query, $visited);
                    }
                } catch (\Throwable $e) {
                    // Fallback to raw SQL if parsing fails
                    return $resolved;
                }

                return $resolved;
            }

            $model = new $modelClass;
            $table = $model->getTable();

            if (str_contains($node->name, '.') && ! Schema::hasColumn($table, $node->name)) {
                if ($query && ! str_contains($node->name, '(')) {
                    $parts = explode('.', $node->name);
                    array_pop($parts);
                    $relationName = implode('.', $parts);
                    $query->with($relationName);

                    // We need to find the FIRST relation in the path to ensure the model has the necessary key
                    $firstRelation = explode('.', $relationName)[0];
                    if (method_exists($model, $firstRelation)) {
                        $relation = $model->{$firstRelation}();
                        if ($relation instanceof BelongsTo) {
                            $query->addSelect($model->getTable().'.'.$relation->getForeignKeyName());
                        } elseif ($relation instanceof HasOne || $relation instanceof HasMany) {
                            $query->addSelect($model->getTable().'.'.$relation->getLocalKeyName());
                        }
                    }
                }

                return 'NULL';
            }

            if ($this->columnRegistry->hasResolver($node->name, $modelClass)) {
                if (! Schema::hasColumn($table, $node->name)) {
                    return 'NULL';
                }
            }

            return $node->name;
        }

        if ($node instanceof LiteralNode) {
            $bindings[] = $node->value;

            return '?';
        }

        if ($node instanceof ArithmeticNode) {
            $left = $this->compileExpression($node->left, $modelClass, $bindings, $context, $query, $visited);
            $right = $this->compileExpression($node->right, $modelClass, $bindings, $context, $query, $visited);

            return "($left {$node->operator} $right)";
        }

        if ($node instanceof CastNode) {
            return $this->compileExpression($node->expression, $modelClass, $bindings, $context, $query, $visited);
        }

        if ($node instanceof AggregateNode) {
            if ($node->expression instanceof ColumnNode) {
                return $this->compileAggregate($query, $node->expression->name, $node->function, $bindings);
            }
        }

        throw new AdvancedSearchException('Unsupported node in arithmetic expression');
    }

    protected function compileAggregate(?Builder $query, string $relation, string $function, array &$bindings): string
    {
        if (! $query) {
            return 'NULL';
        }

        $tempBuilder = $query->getModel()->newQuery();
        if ($function === 'COUNT') {
            $tempBuilder->withCount($relation);
        } else {
            $tempBuilder->withExists($relation);
        }

        $alias = str_replace('.', '_', $relation).($function === 'COUNT' ? '_count' : '_exists');
        $grammar = $query->getQuery()->getGrammar();
        $quotedAlias = $grammar->wrap($alias);

        foreach ($tempBuilder->getQuery()->columns as $column) {
            $sql = ($column instanceof Expression)
                ? (string) $column->getValue($grammar)
                : $grammar->wrap($column);

            if (str_contains(strtolower($sql), ' as '.strtolower($quotedAlias))) {
                $bindings = array_merge($bindings, $tempBuilder->getQuery()->getBindings());

                return '('.preg_replace('/\s+as\s+.*$/i', '', $sql).')';
            }
        }

        return 'NULL';
    }
}
