<?php

namespace AyupCreative\AdvancedSearch\Registry;

use AyupCreative\AdvancedSearch\Attributes\DefaultSelections;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use ReflectionClass;

class ColumnRegistry
{
    protected array $columns = [];

    protected array $modelColumns = [];

    protected array $expressions = [];

    protected array $modelExpressions = [];

    protected array $classColumns = [];

    protected array $columnOperators = [];

    protected array $modelColumnOperators = [];

    protected array $modelDefaultSelections = [];

    protected array $columnMetadata = [];

    protected array $modelColumnMetadata = [];

    public function register(string $name, callable $resolver, array $operators = [], ?string $modelClass = null, array $metadata = [])
    {
        if ($modelClass) {
            $this->modelColumns[$modelClass][$name] = $resolver;
            if (! empty($operators)) {
                $this->modelColumnOperators[$modelClass][$name] = $operators;
            }
            if (! empty($metadata)) {
                $this->modelColumnMetadata[$modelClass][$name] = $metadata;
            }
        } else {
            $this->columns[$name] = $resolver;
            if (! empty($operators)) {
                $this->columnOperators[$name] = $operators;
            }
            if (! empty($metadata)) {
                $this->columnMetadata[$name] = $metadata;
            }
        }
    }

    public function registerExpression(string $name, string $expression, array $operators = [], ?string $modelClass = null, array $metadata = [])
    {
        if ($modelClass) {
            $this->modelExpressions[$modelClass][$name] = $expression;
            if (! empty($operators)) {
                $this->modelColumnOperators[$modelClass][$name] = $operators;
            }
            if (! empty($metadata)) {
                $this->modelColumnMetadata[$modelClass][$name] = $metadata;
            }
        } else {
            $this->expressions[$name] = $expression;
            if (! empty($operators)) {
                $this->columnOperators[$name] = $operators;
            }
            if (! empty($metadata)) {
                $this->columnMetadata[$name] = $metadata;
            }
        }
    }

    public function registerFromClass(string $class)
    {
        if (isset($this->classColumns[$class])) {
            return;
        }

        $reflection = new ReflectionClass($class);

        // Handle default selections
        $defaultSelectionsAttr = $reflection->getAttributes(DefaultSelections::class);
        if (! empty($defaultSelectionsAttr)) {
            $instance = $defaultSelectionsAttr[0]->newInstance();
            $this->modelDefaultSelections[$class] = $instance->columns;
        } elseif (method_exists($class, 'getAdvancedSearchDefaultSelections')) {
            $this->modelDefaultSelections[$class] = $class::getAdvancedSearchDefaultSelections();
        }

        $found = [];

        // Handle class-level attributes (expression-only columns)
        $classAttributes = $reflection->getAttributes(VirtualColumn::class);
        foreach ($classAttributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance->expression) {
                $this->registerExpression($instance->name, $instance->expression, $instance->operators, $class, $instance->metadata);
                $found[] = $instance->name;
            }
        }

        // Handle method-level attributes (resolvers with optional expressions)
        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(VirtualColumn::class);
            foreach ($attributes as $attribute) {
                if (! $method->isStatic()) {
                    throw new AdvancedSearchException("VirtualColumn attribute used on non-static method {$class}::{$method->getName()}. Methods used for search columns must be static.");
                }

                $instance = $attribute->newInstance();

                // Always register the method as a resolver
                $this->register($instance->name, [$class, $method->getName()], $instance->operators, $class, $instance->metadata);

                // If it has an expression, register it too
                if ($instance->expression) {
                    $this->registerExpression($instance->name, $instance->expression, $instance->operators, $class, $instance->metadata);
                }

                $found[] = $instance->name;
            }
        }

        $this->classColumns[$class] = array_unique($found);
    }

    public function getColumnsForClass(string $class): array
    {
        $this->registerFromClass($class);

        return $this->classColumns[$class] ?? [];
    }

    public function getOperators(string $name, ?string $modelClass = null): array
    {
        if ($modelClass && isset($this->modelColumnOperators[$modelClass][$name])) {
            return $this->modelColumnOperators[$modelClass][$name];
        }

        return $this->columnOperators[$name] ?? [];
    }

    public function getMetadata(string $name, ?string $modelClass = null): array
    {
        if ($modelClass) {
            $this->registerFromClass($modelClass);
            if (isset($this->modelColumnMetadata[$modelClass][$name])) {
                return $this->modelColumnMetadata[$modelClass][$name];
            }
        }

        return $this->columnMetadata[$name] ?? [];
    }

    public function getBlankSyntax(string $name, ?string $operator = null): string
    {
        $op = $operator ?? 'equals';
        // Basic templates based on operator name
        if (in_array($op, ['in', 'between'])) {
            return "[$name]:$op()";
        }

        return "[$name]:$op\"\"";
    }

    public function hasResolver(string $name, ?string $modelClass = null): bool
    {
        if ($modelClass && isset($this->modelColumns[$modelClass][$name])) {
            return true;
        }

        return isset($this->columns[$name]);
    }

    public function resolve(string $name, ?string $modelClass = null)
    {
        if ($modelClass) {
            if (isset($this->modelColumns[$modelClass][$name])) {
                return $this->modelColumns[$modelClass][$name];
            }
            if (isset($this->modelExpressions[$modelClass][$name])) {
                return $this->modelExpressions[$modelClass][$name];
            }
        }

        if (isset($this->columns[$name])) {
            return $this->columns[$name];
        }

        if (isset($this->expressions[$name])) {
            return $this->expressions[$name];
        }

        throw new AdvancedSearchException("Unknown search column: $name");
    }

    public function resolveExpression(string $name, ?string $modelClass = null): ?string
    {
        if ($modelClass) {
            $this->registerFromClass($modelClass);
            if (isset($this->modelExpressions[$modelClass][$name])) {
                return $this->modelExpressions[$modelClass][$name];
            }
        }

        return $this->expressions[$name] ?? null;
    }

    public function getDefaultSelections(string $modelClass): array
    {
        $this->registerFromClass($modelClass);

        $cols = $this->modelDefaultSelections[$modelClass] ?? [];

        if (empty($cols) && method_exists($modelClass, 'getFillable')) {
            $cols = (new $modelClass)->getFillable();
        }

        return array_map(function ($col) use ($modelClass) {
            return [
                'name' => $col,
                'label' => $col,
                'is_alias' => $this->resolveExpression($col, $modelClass) !== null,
                'expression' => "[$col]",
                'metadata' => $this->getMetadata($col, $modelClass),
            ];
        }, $cols);
    }

    public function hasDefaultSelections(string $modelClass): bool
    {
        $this->registerFromClass($modelClass);

        return ! empty($this->modelDefaultSelections[$modelClass]);
    }
}
