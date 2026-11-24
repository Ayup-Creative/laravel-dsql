<?php

namespace AyupCreative\AdvancedSearch\Compiler;

use AyupCreative\AdvancedSearch\AST\CastNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\Node;
use AyupCreative\AdvancedSearch\Concerns\ResolvesRelationships;
use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

class PredicateCompiler
{
    use ResolvesRelationships;

    public function __construct(protected ColumnRegistry $columns, protected ExpressionCompiler $expressions) {}

    public function resolver(Builder $query, Node $column, array $context = [], array $visited = [], ?CompiledExpression $right = null): callable
    {
        if ($column instanceof CastNode) {
            return $this->resolver($query, $column->expression, $context, $visited, $right);
        }
        if ($column instanceof ColumnNode) {
            $name = $column->name;
            $key = $query->getModel()::class.':'.$name;
            if (in_array($key, $visited, true)) {
                throw new AdvancedSearchException("Circular column or alias: {$name}");
            }
            $visited[] = $key;
            if (isset($context[$name])) {
                return $this->resolver($query, $context[$name], $context, $visited, $right);
            }
            $modelClass = $query->getModel()::class;
            $this->columns->registerFromClass($modelClass);
            if ($this->columns->hasResolver($name, $modelClass)) {
                $resolver = $this->columns->resolve($name, $modelClass);

                return function (Builder $q, string $op, $val) use ($resolver, $right, $query, $context, $modelClass) {
                    $rhs = $right;
                    if (! $rhs && str_ends_with($op, '_column') &&
                        (isset($context[$val]) || str_contains($val, '.') || $this->columns->resolveExpression($val, $modelClass) !== null)) {
                        $rhs = $this->expressions->compile($query, new ColumnNode($val), $context);
                    }
                    $resolver($q, $op, $rhs ? new Expression($rhs->sql) : $val);
                    if ($rhs) {
                        $q->addBinding($rhs->bindings, 'where');
                    }
                };
            }
            $expression = $this->columns->resolveExpression($name, $modelClass);
            if ($expression !== null && ($parsed = $this->expressions->parseExpression($expression))) {
                return $this->resolver($query, $parsed, $context, $visited, $right);
            }
            if ($expression === null && str_contains($name, '.') && ! str_starts_with($name, $query->getModel()->getTable().'.')) {
                $parts = explode('.', $name);
                $actualColumn = array_pop($parts);
                $model = $query->getModel();
                foreach ($parts as $part) {
                    $model = $this->resolveRelationship($model, $part)->getRelated();
                }

                return function (Builder $q, string $op, $val) use ($parts, $actualColumn, $query, $context, $right) {
                    $rhs = $right;
                    if (str_ends_with($op, '_column') && ! $rhs) {
                        $rhs = $this->expressions->compile($query, new ColumnNode($val), $context);
                    }
                    $q->whereHas(implode('.', $parts), function (Builder $related) use ($actualColumn, $op, $val, $rhs) {
                        $resolver = $this->resolver($related, new ColumnNode($actualColumn), [], [], $rhs);
                        $resolver($related, $op, $val);
                    });
                };
            }
        }

        $left = $this->expressions->compile($query, $column, $context);

        return function (Builder $q, string $op, $val) use ($left, $query, $context, $right) {
            if (str_ends_with($op, '_column')) {
                $rhs = $right ?? $this->expressions->compile($query, new ColumnNode($val), $context);
                $operator = substr($op, 0, -7);
                $q->whereRaw("{$left->sql} {$operator} {$rhs->sql}", [...$left->bindings, ...$rhs->bindings]);
            } elseif ($op === 'in') {
                $values = (array) $val;
                if ($values === []) {
                    $q->whereRaw('0 = 1');
                } else {
                    $placeholders = implode(', ', array_fill(0, count($values), '?'));
                    $q->whereRaw("{$left->sql} IN ({$placeholders})", [...$left->bindings, ...$values]);
                }
            } elseif ($op === 'between') {
                $q->whereRaw("{$left->sql} BETWEEN ? AND ?", [...$left->bindings, ...$val]);
            } elseif ($op === '=' && $val === null) {
                $q->whereRaw("{$left->sql} IS NULL", $left->bindings);
            } else {
                $q->whereRaw("{$left->sql} {$op} ?", [...$left->bindings, $val]);
            }
        };
    }

    public function validateValue(string $operator, array $value): void
    {
        $type = $value['type'] ?? null;
        $data = $value['value'] ?? null;
        $literal = fn ($item) => $item === null || is_scalar($item) || $item instanceof \DateTimeInterface || $item instanceof \Stringable;
        if (in_array($operator, ['equals', 'in', 'gt', 'lt', 'between', 'contains'], true) &&
            (($type === 'scalar' && ! $literal($data)) ||
                ($type === 'list' && is_array($data) && array_filter($data, fn ($item) => ! $literal($item)) !== []))) {
            throw new AdvancedSearchException("Expected literal values for operator: {$operator}");
        }
        if (! in_array($type, ['scalar', 'column', 'list'], true) ||
            ($type === 'list' && (! is_array($data) || count(array_filter($data, fn ($item) => is_array($item))) > 0)) ||
            (in_array($operator, ['gt', 'lt', 'contains'], true) && $type === 'list') ||
            (in_array($operator, ['gt', 'lt'], true) && $data === null) ||
            ($operator === 'contains' && ($type !== 'scalar' || $data === null)) ||
            ($operator === 'between' && ($type !== 'list' || count($data) !== 2)) ||
            ($operator === 'in' && $type === 'column')) {
            throw new AdvancedSearchException("Invalid value for operator: {$operator}");
        }
    }
}
