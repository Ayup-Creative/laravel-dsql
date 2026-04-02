<?php

namespace AyupCreative\AdvancedSearch\Compiler;

use AyupCreative\AdvancedSearch\AST\AggregateNode;
use AyupCreative\AdvancedSearch\AST\ArithmeticNode;
use AyupCreative\AdvancedSearch\AST\CastNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\LiteralNode;
use AyupCreative\AdvancedSearch\AST\Node;
use Illuminate\Database\Eloquent\Model;

class Evaluator
{
    public function evaluate(Node $node, Model $model): mixed
    {
        if ($node instanceof LiteralNode) {
            return $node->value;
        }

        if ($node instanceof ColumnNode) {
            $value = $this->resolveColumn($node->name, $model);

            // Handle plural relations
            if ($value === null && str_contains($node->name, '.')) {
                $parts = explode('.', $node->name);
                for ($i = 0; $i < count($parts) - 1; $i++) {
                    $testPath = implode('.', array_slice($parts, 0, $i + 1)).'.*.'.implode('.', array_slice($parts, $i + 1));
                    $testValue = data_get($model, $testPath);
                    if ($testValue !== null && $testValue !== []) {
                        return $testValue;
                    }
                }
            }

            return $value;
        }

        if ($node instanceof ArithmeticNode) {
            $left = $this->evaluate($node->left, $model);
            $right = $this->evaluate($node->right, $model);

            if ($left === null || $right === null) {
                return null;
            }

            switch ($node->operator) {
                case '+': return $left + $right;
                case '-': return $left - $right;
                case '*': return $left * $right;
                case '/': return $right != 0 ? $left / $right : 0;
            }
        }

        if ($node instanceof CastNode) {
            // We only evaluate the inner expression here.
            // The actual PHP-side casting is handled by AdvancedSearch::casts()->cast()
            // which is called in the Searchable trait's getSelectionValue method.
            return $this->evaluate($node->expression, $model);
        }

        if ($node instanceof AggregateNode) {
            if ($node->expression instanceof ColumnNode) {
                $relation = $node->expression->name;
                $attribute = str_replace('.', '_', $relation);

                if ($node->function === 'COUNT') {
                    return $model->getAttribute("{$attribute}_count");
                }
                if ($node->function === 'EXISTS') {
                    return $model->getAttribute("{$attribute}_exists");
                }
            }
        }

        return null;
    }

    protected function resolveColumn(string $name, Model $model): mixed
    {
        if (! str_contains($name, '.')) {
            if (method_exists($model, 'getSelectionValue')) {
                // We use getAttribute first to avoid infinite recursion
                // if getSelectionValue calls Evaluator again for the same column
                $val = $model->getAttribute($name);
                if ($val !== null) {
                    return $val;
                }

                return $model->getSelectionValue($name);
            }

            return $model->getAttribute($name);
        }

        $parts = explode('.', $name);
        $current = $model;

        foreach ($parts as $index => $part) {
            $isLast = $index === count($parts) - 1;

            if ($isLast) {
                if ($current instanceof Model && method_exists($current, 'getSelectionValue')) {
                    return $current->getSelectionValue($part);
                }

                return data_get($current, $part);
            }

            $current = data_get($current, $part);

            if ($current === null) {
                return null;
            }
        }

        return null;
    }
}
