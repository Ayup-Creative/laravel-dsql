<?php

namespace AyupCreative\AdvancedSearch\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

trait DiscoversSearchSchema
{
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
                $return = $this->resolveRelationship($instance, $name);
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
}
