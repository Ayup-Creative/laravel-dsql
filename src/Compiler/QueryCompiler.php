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
use AyupCreative\AdvancedSearch\Concerns\ResolvesRelationships;
use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use AyupCreative\AdvancedSearch\Registry\DynamicValueRegistry;
use AyupCreative\AdvancedSearch\Registry\OperatorRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\Schema;

class QueryCompiler
{
    use ResolvesRelationships;

    public function __construct(
        protected ColumnRegistry $columnRegistry,
        protected OperatorRegistry $operatorRegistry,
        protected DynamicValueRegistry $dynamicValueRegistry
    ) {}

    public function validate(Node $node, string $modelClass, array $context = []): void
    {
        $this->compile((new $modelClass)->newQuery(), $node, $context);
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
                        $mapping = $field['expression'];
                        while ($mapping instanceof CastNode) {
                            $mapping = $mapping->expression;
                        }
                        $isSimpleMapping = $mapping instanceof ColumnNode && $mapping->name === $alias;
                        if (! $isSimpleMapping) {
                            $localContext[$alias] = $field['expression'];
                        }

                        // An alias is one identifier, including reserved words and dots.
                        $grammar = $query->getQuery()->getGrammar();
                        $wrapper = ($grammar instanceof MySqlGrammar) ? '`' : '"';
                        $escaped = str_replace($wrapper, $wrapper.$wrapper, $alias);
                        $sql .= " AS {$wrapper}{$escaped}{$wrapper}";
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
                $expression = (new ExpressionCompiler($this->columnRegistry))->compile($query, new ColumnNode($sort['column']), $localContext ?? []);
                $query->orderByRaw($expression->sql.' '.$sort['direction'], $expression->bindings);
            }

            if ($node->limit !== null) {
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

            $query->where(function ($q) use ($node, $context, $method) {
                $this->compile($q, $node->left, $context);
                $q->$method(fn ($right) => $this->compile($right, $node->right, $context));
            });

            return;
        }

        if ($node instanceof ConditionNode) {
            $modelClass = get_class($query->getModel());
            $columnResolver = $this->getResolver($query, $node->column, $modelClass, $context);
            $operator = $this->operatorRegistry->resolve($node->operator);

            $resolvedValue = $this->resolveValue($node->value);
            (new PredicateCompiler($this->columnRegistry, new ExpressionCompiler($this->columnRegistry)))->validateValue($node->operator, $resolvedValue);

            $operator->apply($query, $columnResolver, $resolvedValue);

            return;
        }

        if ($node instanceof AggregateNode) {
            $expression = (new ExpressionCompiler($this->columnRegistry))->compile($query, $node, $context);
            $query->whereRaw($expression->sql.' > ?', [...$expression->bindings, 0]);

            return;
        }

        throw new AdvancedSearchException('A predicate must contain an operator or relationship aggregate.');
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
        if (is_array($value) && ($value['type'] ?? null) === 'column') {
            throw new AdvancedSearchException('Column references cannot be used as literal list values.');
        }
        $resolved = $this->resolveValue($value);

        return is_array($resolved) && isset($resolved['value']) ? $resolved['value'] : $resolved;
    }

    protected function getResolver(Builder $query, string|Node $column, string $modelClass, array $context = []): callable
    {
        $compiler = new PredicateCompiler($this->columnRegistry, new ExpressionCompiler($this->columnRegistry));

        return $compiler->resolver($query, is_string($column) ? new ColumnNode($column) : $column, $context);
    }

    protected function compileExpression(Node $node, string $modelClass, array &$bindings, array $context = [], ?Builder $query = null, array $visited = []): string
    {
        if ($node instanceof ColumnNode) {
            if (isset($context[$node->name])) {
                if (in_array($node->name, $visited)) {
                    throw new AdvancedSearchException("Circular column or alias: {$node->name}");
                }

                $visited[] = $node->name;

                return $this->compileExpression($context[$node->name], $modelClass, $bindings, $context, $query, $visited);
            }

            $resolved = $this->columnRegistry->resolveExpression($node->name, $modelClass);

            if ($resolved) {
                if (in_array($node->name, $visited, true)) {
                    throw new AdvancedSearchException("Circular column or alias: {$node->name}");
                }
                $visited[] = $node->name;
                $parsed = (new ExpressionCompiler($this->columnRegistry))->parseExpression($resolved);

                return $parsed ? $this->compileExpression($parsed, $modelClass, $bindings, $context, $query, $visited) : $resolved;
            }

            $model = new $modelClass;
            $table = $model->getTable();
            if (str_starts_with($node->name, $table.'.')) {
                return (new ExpressionCompiler($this->columnRegistry))->compile($query ?? $model->newQuery(), $node, $context)->sql;
            }

            if (str_contains($node->name, '.') && ! Schema::hasColumn($table, $node->name)) {
                if ($query && ! str_contains($node->name, '(')) {
                    $parts = explode('.', $node->name);
                    array_pop($parts);
                    $relationName = implode('.', $parts);
                    $relatedModel = $model;
                    foreach ($parts as $part) {
                        $relatedModel = $this->resolveRelationship($relatedModel, $part)->getRelated();
                    }
                    $actualColumn = substr($node->name, strrpos($node->name, '.') + 1);
                    $relatedClass = $relatedModel::class;
                    $this->columnRegistry->registerFromClass($relatedClass);
                    if (! $relatedModel->getConnection()->getSchemaBuilder()->hasColumn($relatedModel->getTable(), $actualColumn) &&
                        ! $this->columnRegistry->hasResolver($actualColumn, $relatedClass) &&
                        $this->columnRegistry->resolveExpression($actualColumn, $relatedClass) === null) {
                        throw new AdvancedSearchException("Unknown related column: {$node->name}");
                    }
                    $query->with($relationName);

                    // We need to find the FIRST relation in the path to ensure the model has the necessary key
                    $firstRelation = explode('.', $relationName)[0];
                    $relation = $this->resolveRelationship($model, $firstRelation);
                    if ($relation instanceof BelongsTo) {
                        $query->addSelect($model->getTable().'.'.$relation->getForeignKeyName());
                    } elseif ($relation instanceof HasOne || $relation instanceof HasMany) {
                        $query->addSelect($model->getTable().'.'.$relation->getLocalKeyName());
                    }
                }

                return 'NULL';
            }

            if ($this->columnRegistry->hasResolver($node->name, $modelClass)) {
                if (! Schema::hasColumn($table, $node->name)) {
                    return 'NULL';
                }
            }

            (new ExpressionCompiler($this->columnRegistry))->compile($query ?? $model->newQuery(), $node, $context);

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
            $expression = (new ExpressionCompiler($this->columnRegistry))->compile($query ?? (new $modelClass)->newQuery(), $node, $context);
            $bindings = array_merge($bindings, $expression->bindings);

            return $expression->sql;
        }

        throw new AdvancedSearchException('Unsupported node in selection expression');
    }
}
