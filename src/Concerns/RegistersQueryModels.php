<?php

namespace AyupCreative\AdvancedSearch\Concerns;

use AyupCreative\AdvancedSearch\AST\AggregateNode;
use AyupCreative\AdvancedSearch\AST\ArithmeticNode;
use AyupCreative\AdvancedSearch\AST\CastNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\ConditionNode;
use AyupCreative\AdvancedSearch\AST\LogicalNode;
use AyupCreative\AdvancedSearch\AST\Node;
use AyupCreative\AdvancedSearch\AST\QueryNode;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use Illuminate\Database\Eloquent\Relations\Relation;

trait RegistersQueryModels
{
    use ResolvesRelationships;

    protected function registerModelsFromAst(?Node $node, string $modelClass): void
    {
        if (! $node) {
            return;
        }

        if ($node instanceof QueryNode) {
            foreach ($node->fields as $field) {
                $this->registerModelsFromExpression($field['expression'], $modelClass);
            }
            if ($node->criteria) {
                $this->registerModelsFromAst($node->criteria, $modelClass);
            }
        } elseif ($node instanceof LogicalNode) {
            $this->registerModelsFromAst($node->left, $modelClass);
            $this->registerModelsFromAst($node->right, $modelClass);
        } elseif ($node instanceof ConditionNode) {
            $this->registerModelsFromExpression($node->column, $modelClass);
        }
    }

    protected function registerModelsFromExpression(Node $node, string $modelClass): void
    {
        if ($node instanceof ColumnNode) {
            if (str_contains($node->name, '.')) {
                $parts = explode('.', $node->name);
                array_pop($parts);
                $currentModelClass = $modelClass;

                foreach ($parts as $part) {
                    if (! class_exists($currentModelClass)) {
                        break;
                    }

                    $instance = new $currentModelClass;
                    if (method_exists($instance, $part) || $instance->relationResolver($currentModelClass, $part)) {
                        try {
                            $relation = $this->resolveRelationship($instance, $part);
                            if ($relation instanceof Relation) {
                                $relatedModelClass = get_class($relation->getRelated());
                                $this->columnRegistry->registerFromClass($relatedModelClass);
                                $currentModelClass = $relatedModelClass;
                            } else {
                                break;
                            }
                        } catch (\Throwable $e) {
                            break;
                        }
                    } else {
                        break;
                    }
                }
            } else {
                $resolved = $this->columnRegistry->resolveExpression($node->name, $modelClass);
                if ($resolved) {
                    try {
                        $lexer = new Lexer($resolved);
                        $parser = new PrattParser($lexer->tokenize());
                        $ast = $parser->parse();

                        if ($ast instanceof QueryNode && $ast->criteria) {
                            $this->registerModelsFromAst($ast->criteria, $modelClass);
                        }
                    } catch (\Throwable $e) {
                        // Fallback
                    }
                }
            }
        } elseif ($node instanceof ArithmeticNode) {
            $this->registerModelsFromExpression($node->left, $modelClass);
            $this->registerModelsFromExpression($node->right, $modelClass);
        } elseif ($node instanceof CastNode) {
            $this->registerModelsFromExpression($node->expression, $modelClass);
        } elseif ($node instanceof AggregateNode) {
            $this->registerModelsFromExpression($node->expression, $modelClass);
        }
    }
}
