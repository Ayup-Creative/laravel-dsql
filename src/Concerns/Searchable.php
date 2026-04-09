<?php

namespace AyupCreative\AdvancedSearch\Concerns;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\AST\QueryNode;
use AyupCreative\AdvancedSearch\Compiler\Evaluator;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use Illuminate\Support\Str;

trait Searchable
{
    /**
     * @return array<int, array{name: string, label: string, is_alias: bool, expression: string, cast: string|null, metadata: array}>
     */
    public function getSelections(string $query = ''): array
    {
        /** @var AdvancedSearch $search */
        $search = app(AdvancedSearch::class);

        return $search->getSelections($query, static::class);
    }

    /**
     * @param  array{cast?: string|null, expression?: string}|null  $selection
     * @return mixed
     */
    public function getSelectionValue(string $column, ?array $selection = null)
    {
        // Try to get as direct attribute first
        $value = $this->getAttribute($column);

        // If it's null, it might be a virtual column or relationship path
        if ($value === null) {
            // Prefer explicit expression from selection metadata; otherwise, resolve from registry if the column is virtual
            /** @var AdvancedSearch $search */
            $search = app(AdvancedSearch::class);
            $resolvedExpr = $selection['expression'] ?? $search->columns()->resolveExpression($column, static::class);

            if ($resolvedExpr === null && ! str_contains($column, '.')) {
                // Not a virtual column and no relationship path; attribute already null → avoid recursion
                return null;
            }

            $resolvedExpr = $resolvedExpr ?? "[$column]";

            try {
                // Fast-path: handle simple SQL CONCAT(arg1, 'literal', argN) without full parsing
                if (preg_match('/^CONCAT\s*\((.*)\)$/i', $resolvedExpr, $m)) {
                    $argsRaw = $m[1];
                    $parts = array_map('trim', explode(',', $argsRaw));
                    $computed = '';
                    foreach ($parts as $p) {
                        // Quoted literal
                        if ((str_starts_with($p, "'") && str_ends_with($p, "'")) || (str_starts_with($p, '"') && str_ends_with($p, '"'))) {
                            $computed .= trim($p, "'\"");
                        } else {
                            $computed .= (string) data_get($this, $p);
                        }
                    }
                    $value = $computed;
                } else {
                    $lexer = new Lexer($resolvedExpr);
                    $parser = new PrattParser($lexer->tokenize());
                    $ast = $parser->parse();

                    if ($ast instanceof QueryNode) {
                        if (! empty($ast->fields)) {
                            $ast = $ast->fields[0]['expression'];
                        } elseif ($ast->criteria) {
                            $ast = $ast->criteria;
                        }
                    }

                    $evaluator = new Evaluator;
                    $value = $evaluator->evaluate($ast, $this);
                }
            } catch (\Throwable $e) {
                // Fallback for simple column ref if parsing fails for some reason
                if (preg_match('/^\[([a-zA-Z0-9_\.\-]+)\]$/', $resolvedExpr, $matches)) {
                    $value = data_get($this, $matches[1]);
                }
            }
        }

        // Apply PHP-side casting if specified
        if ($value !== null && $selection && ! empty($selection['cast'])) {
            /** @var AdvancedSearch $search */
            $search = app(AdvancedSearch::class);
            $value = $search->casts()->cast($selection['cast'], $value, $this);
        }

        // Format value if a formatting method exists
        $method = 'format'.Str::studly($column).'SearchValue';
        if (method_exists($this, $method)) {
            return $this->$method($value);
        }

        return $value;
    }
}
