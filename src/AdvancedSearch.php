<?php

namespace AyupCreative\AdvancedSearch;

use AyupCreative\AdvancedSearch\AST\AggregateNode;
use AyupCreative\AdvancedSearch\AST\ArithmeticNode;
use AyupCreative\AdvancedSearch\AST\CastNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\ConditionNode;
use AyupCreative\AdvancedSearch\AST\LogicalNode;
use AyupCreative\AdvancedSearch\AST\Node;
use AyupCreative\AdvancedSearch\AST\QueryNode;
use AyupCreative\AdvancedSearch\Compiler\QueryCompiler;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use AyupCreative\AdvancedSearch\Registry\CastRegistry;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use AyupCreative\AdvancedSearch\Registry\DynamicValueRegistry;
use AyupCreative\AdvancedSearch\Registry\OperatorRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

class AdvancedSearch
{
    public function __construct(
        protected ColumnRegistry $columnRegistry,
        protected OperatorRegistry $operatorRegistry,
        protected DynamicValueRegistry $dynamicValueRegistry,
        protected CastRegistry $castRegistry
    ) {}

    public function apply(string|Builder|Model $query, string $input): Builder
    {
        $query = $this->resolveBuilder($query);
        $modelClass = get_class($query->getModel());
        $this->columnRegistry->registerFromClass($modelClass);

        $lexer = new Lexer($input);
        $tokens = $lexer->tokenize();

        $parser = new PrattParser($tokens);
        $ast = $parser->parse();

        $this->registerModelsFromAst($ast, $modelClass);

        $compiler = new QueryCompiler(
            $this->columnRegistry,
            $this->operatorRegistry,
            $this->dynamicValueRegistry
        );
        $compiler->compile($query, $ast);

        return $query;
    }

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
                    if (method_exists($instance, $part)) {
                        try {
                            $relation = $instance->$part();
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

    public function validate(string $input, string|Builder|Model $model): void
    {
        $modelClass = $this->resolveModelClass($model);
        $this->columnRegistry->registerFromClass($modelClass);

        $lexer = new Lexer($input);
        $tokens = $lexer->tokenize();

        $parser = new PrattParser($tokens);
        $ast = $parser->parse();

        $compiler = new QueryCompiler(
            $this->columnRegistry,
            $this->operatorRegistry,
            $this->dynamicValueRegistry
        );

        $compiler->validate($ast, $modelClass);
    }

    public function columns(): ColumnRegistry
    {
        return $this->columnRegistry;
    }

    public function operators(): OperatorRegistry
    {
        return $this->operatorRegistry;
    }

    public function dynamicValues(): DynamicValueRegistry
    {
        return $this->dynamicValueRegistry;
    }

    public function casts(): CastRegistry
    {
        return $this->castRegistry;
    }

    /**
     * @return string[]
     */
    public function getAvailableDynamicValues(): array
    {
        return array_map(fn ($name) => "{$name}()", $this->dynamicValueRegistry->getNames());
    }

    /**
     * @param  class-string|Builder|Model  $model
     * @return string[]
     */
    public function getAutocomplete(string|Builder|Model $model): array
    {
        return $this->columnRegistry->getColumnsForClass($this->resolveModelClass($model));
    }

    /**
     * @param  class-string|Builder|Model  $model
     * @return array<int, array{name: string, type: 'column'|'relationship', model?: string}>
     */
    public function getSchema(string|Builder|Model $model): array
    {
        $modelClass = $this->resolveModelClass($model);
        /** @var Model $instance */
        $instance = new $modelClass;
        $results = [];

        // 1. Database columns
        $table = $instance->getTable();
        $dbColumns = Schema::getColumnListing($table);
        foreach ($dbColumns as $col) {
            $results[$col] = [
                'name' => $col,
                'type' => 'column',
            ];
        }

        // 2. Virtual columns
        $virtual = $this->columnRegistry->getColumnsForClass($modelClass);
        foreach ($virtual as $v) {
            // Only add top-level names (no dots)
            if (! str_contains($v, '.')) {
                $results[$v] = [
                    'name' => $v,
                    'type' => 'column',
                ];
            } else {
                // If it's a dotted path, add the root part as a possible relationship/column
                $parts = explode('.', $v);
                $relName = $parts[0];
                if (! isset($results[$relName])) {
                    $results[$relName] = [
                        'name' => $relName,
                        'type' => 'relationship',
                        'model' => null,
                    ];
                }
            }
        }

        // 3. Relationships (one level deep)
        $reflection = new \ReflectionClass($modelClass);
        $baseModelClass = Model::class;

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip methods from base Model class or with parameters or static
            if ($method->getDeclaringClass()->getName() === $baseModelClass ||
                $method->isStatic() ||
                $method->getNumberOfParameters() > 0) {
                continue;
            }

            // Skip common trait methods if possible, or just try-catch
            $name = $method->getName();
            if (str_starts_with($name, 'get') || str_starts_with($name, 'scope')) {
                continue;
            }

            try {
                // We use a fresh model instance to avoid any state issues
                $return = $method->invoke($instance);
                if ($return instanceof Relation) {
                    $results[$name] = [
                        'name' => $name,
                        'type' => 'relationship',
                        'model' => get_class($return->getRelated()),
                    ];
                }
            } catch (\Throwable $e) {
                // Ignore methods that can't be called
            }
        }

        ksort($results);

        return array_values($results);
    }

    public function getBlankSyntax(string $column, ?string $operator = null): string
    {
        return $this->columnRegistry->getBlankSyntax($column, $operator);
    }

    /**
     * @return string[]
     */
    public function getAvailableOperators(?string $column = null, string|Builder|Model|null $model = null): array
    {
        $modelClass = $this->resolveModelClass($model);

        if ($modelClass) {
            $this->columnRegistry->registerFromClass($modelClass);
        }

        if ($column) {
            $specific = $this->columnRegistry->getOperators($column, $modelClass);
            if (! empty($specific)) {
                return $specific;
            }
        }

        return $this->operatorRegistry->getNames();
    }

    /**
     * @return array<int, array{name: string, label: string, is_alias: bool, expression: string, cast: string|null, metadata: array}>
     */
    public function getSelections(string $input, string|Builder|Model|null $model = null): array
    {
        $modelClass = $this->resolveModelClass($model);
        $lexer = new Lexer($input);
        $tokens = $lexer->tokenize();

        $parser = new PrattParser($tokens);
        $ast = $parser->parse();

        if ($ast instanceof QueryNode && ! empty($ast->fields)) {
            $selections = [];
            $localContext = [];
            foreach ($ast->fields as $field) {
                /** @var Node $expression */
                $expression = $field['expression'];
                $alias = $field['alias'];

                $originalColumnName = ($expression instanceof ColumnNode) ? $expression->name : null;

                // If expression is a virtual column, resolve it for better metadata/expression extraction
                if ($modelClass && $expression instanceof ColumnNode) {
                    $resolved = $this->columnRegistry->resolveExpression($expression->name, $modelClass);
                    if ($resolved) {
                        try {
                            $lexer = new Lexer($resolved);
                            $parser = new PrattParser($lexer->tokenize());
                            $astExpr = $parser->parse();
                            if ($astExpr instanceof QueryNode && $astExpr->criteria) {
                                $expression = $astExpr->criteria;
                            }
                        } catch (\Throwable $e) {
                            // Fallback to original
                        }
                    }
                }

                // Resolve expression if it refers to a local alias
                $resolvedForMetadata = $expression;
                $visited = [];
                while ($resolvedForMetadata instanceof ColumnNode && isset($localContext[$resolvedForMetadata->name])) {
                    if (in_array($resolvedForMetadata->name, $visited)) {
                        break;
                    }
                    $visited[] = $resolvedForMetadata->name;

                    $resolvedForMetadata = $localContext[$resolvedForMetadata->name];
                }

                $cast = null;
                if ($resolvedForMetadata instanceof CastNode) {
                    $cast = $resolvedForMetadata->type;
                }

                $name = $alias;
                if (! $name) {
                    $innerExpression = $expression;
                    while ($innerExpression instanceof CastNode) {
                        $innerExpression = $innerExpression->expression;
                    }

                    if ($innerExpression instanceof ColumnNode) {
                        $name = $innerExpression->name;
                    } else {
                        $name = (string) $expression;
                    }
                }

                if ($alias) {
                    $isSimpleMapping = $expression instanceof ColumnNode && $expression->name === $alias;
                    if (! $isSimpleMapping) {
                        $localContext[$alias] = $expression;
                    }
                }

                $metadata = [];
                if ($modelClass) {
                    if ($alias) {
                        $metadata = $this->columnRegistry->getMetadata($alias, $modelClass);
                    } elseif ($originalColumnName) {
                        $metadata = $this->columnRegistry->getMetadata($originalColumnName, $modelClass);
                    } elseif ($innerExpression instanceof ColumnNode) {
                        $metadata = $this->columnRegistry->getMetadata($innerExpression->name, $modelClass);
                    }
                }

                if (empty($metadata) && $modelClass && $resolvedForMetadata instanceof ColumnNode) {
                    $metadata = $this->columnRegistry->getMetadata($resolvedForMetadata->name, $modelClass);
                }

                if ($cast) {
                    $metadata['cast'] = $cast;
                }

                $selections[] = [
                    'name' => $name,
                    'label' => $alias ?: $name,
                    'is_alias' => ! empty($alias),
                    'expression' => (string) $expression,
                    'cast' => $cast,
                    'metadata' => $metadata,
                ];
            }

            return $selections;
        }

        if ($modelClass) {
            $defaults = $this->columnRegistry->getDefaultSelections($modelClass);

            return array_map(function ($selection) {
                if (! isset($selection['cast'])) {
                    $selection['cast'] = null;
                }

                return $selection;
            }, $defaults);
        }

        return [];
    }

    /**
     * @return string[]
     */
    public function suggest(string $input, string|Builder|Model $model): array
    {
        $modelClass = $this->resolveModelClass($model);
        if ($this->isInsideQuotes($input)) {
            return [];
        }

        // 1. Inside sort(
        if (preg_match('/sort\(\s*([a-zA-Z0-9_\.\-]*)$/i', $input, $matches)) {
            $current = $matches[1];
            $columns = $this->getAutocomplete($modelClass);

            return array_values(array_filter($columns, fn ($c) => str_starts_with($c, $current)));
        }

        // 2. Direction for sort(col,
        if (preg_match('/sort\(\s*[a-zA-Z0-9_\.\-]+\s*,\s*([a-zA-Z]*)$/i', $input, $matches)) {
            $current = strtolower($matches[1]);
            $options = ['asc', 'desc'];

            return array_values(array_filter($options, fn ($o) => str_starts_with($o, $current)));
        }

        // 0. Start of query
        if (trim($input) === '') {
            return ['SELECT', '[', 'CAST('];
        }

        // 0.11 Inside CAST(
        if (preg_match('/CAST\(\s*$/i', $input)) {
            return ['['];
        }

        // 0.12 After comma in CAST(
        if (preg_match('/CAST\([^,]+,\s*$/i', $input)) {
            return array_map(fn ($c) => "\"$c\"", $this->castRegistry->getNames());
        }

        // 0.1 After SELECT or comma in SELECT
        if (preg_match('/(SELECT|,)\s*$/i', $input)) {
            return ['[', 'CAST('];
        }

        // 0.2 After AS
        if (preg_match('/AS\s+$/i', $input)) {
            return ['"', '['];
        }

        // 3. Inside [column reference
        if (preg_match('/\[([a-zA-Z0-9_\.\-]*)$/', $input, $matches)) {
            $current = $matches[1];
            $columns = $this->getAutocomplete($modelClass);

            return array_values(array_filter($columns, fn ($c) => str_starts_with($c, $current)));
        }

        // 5. After : typing operator
        if (preg_match('/\[([a-zA-Z0-9_\.\-]+)\]:([a-zA-Z0-9_\.\-]*)$/', $input, $matches)) {
            $column = $matches[1];
            $currentOp = $matches[2];
            $operators = $this->getAvailableOperators($column, $modelClass);

            if (in_array($currentOp, $operators)) {
                if (in_array($currentOp, ['in', 'between'])) {
                    return ['('];
                }

                return array_merge(['"', '['], $this->getAvailableDynamicValues());
            }

            return array_values(array_filter($operators, fn ($o) => str_starts_with($o, $currentOp)));
        }

        // 6. After operator name + space
        if (preg_match('/\[([a-zA-Z0-9_\.\-]+)\]:([a-zA-Z0-9_\.\-]+)\s+$/', $input, $matches)) {
            $op = $matches[2];
            $operators = $this->getAvailableOperators($matches[1], $modelClass);
            if (in_array($op, $operators)) {
                if (in_array($op, ['in', 'between'])) {
                    return ['('];
                }

                return array_merge(['"', '['], $this->getAvailableDynamicValues());
            }
        }

        // 6.1 Typing a dynamic value name after operator
        if (preg_match('/\]:([a-zA-Z0-9_\.\-]+)\s+([a-zA-Z0-9_\-]*)$/', $input, $matches)) {
            $current = $matches[2];
            $dynamics = $this->getAvailableDynamicValues();

            return array_values(array_filter($dynamics, fn ($d) => str_starts_with($d, $current)));
        }

        // 7. Boolean operators and keywords typing
        if (preg_match('/\s+([a-zA-Z]*)$/', $input, $matches)) {
            $current = strtolower($matches[1]);
            if ($current === 'and' || $current === 'or') {
                return ['['];
            }
            $options = ['and', 'or', 'sort(', 'limit('];
            $filtered = array_values(array_filter($options, fn ($o) => str_starts_with($o, $current)));
            if (! empty($filtered)) {
                return array_map(fn ($f) => in_array($f, ['and', 'or']) ? strtoupper($f) : $f, $filtered);
            }
        }

        // 8. General suggestions: start of expression or after boolean logic/grouping
        if (trim($input) === '' || preg_match('/(AND|OR|NOT)\s+$/i', $input) || preg_match('/\(\s*$/', $input)) {
            return ['['];
        }

        // 9. End of expression (likely has a value)
        if (preg_match('/(["\)]|[a-zA-Z0-9\]])\s*$/', $input)) {
            $options = ['AND', 'OR', 'sort(', 'limit('];
            if (! str_contains($input, ':') && ! preg_match('/WHERE/i', $input)) {
                // We're likely in the SELECT part or just starting a where without operator
                if (preg_match('/^SELECT/i', $input)) {
                    $options = array_merge(['AS', ',', 'WHERE', '+', '-', '*', '/'], $options);
                } else {
                    $options = array_merge([':', '+', '-', '*', '/'], $options);
                }
            } else {
                // We're in WHERE or already have a colon
                $options = array_merge(['AND', 'OR', 'sort(', 'limit('], []);
                if (! str_contains($input, ':')) {
                    $options = array_merge([':', '+', '-', '*', '/'], $options);
                }
            }

            return array_unique($options);
        }

        return ['['];
    }

    protected function isInsideQuotes(string $input): bool
    {
        $len = strlen($input);
        $inDouble = false;
        $inSingle = false;
        for ($i = 0; $i < $len; $i++) {
            $char = $input[$i];
            if ($char === '"' && ! $inSingle) {
                if ($i === 0 || $input[$i - 1] !== '\\') {
                    $inDouble = ! $inDouble;
                }
            } elseif ($char === "'" && ! $inDouble) {
                if ($i === 0 || $input[$i - 1] !== '\\') {
                    $inSingle = ! $inSingle;
                }
            }
        }

        return $inDouble || $inSingle;
    }

    protected function resolveBuilder(string|Builder|Model $query): Builder
    {
        if (is_string($query)) {
            return $query::query();
        }

        if ($query instanceof Model) {
            return $query->newQuery();
        }

        return $query;
    }

    protected function resolveModelClass(string|Builder|Model|null $model): ?string
    {
        if ($model instanceof Builder) {
            return get_class($model->getModel());
        }

        if ($model instanceof Model) {
            return get_class($model);
        }

        return $model;
    }
}
