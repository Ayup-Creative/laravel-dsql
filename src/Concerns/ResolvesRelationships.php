<?php

namespace AyupCreative\AdvancedSearch\Concerns;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

trait ResolvesRelationships
{
    protected function resolveRelationship(Model $model, string $name): Relation
    {
        if (method_exists($model, $name)) {
            $method = new ReflectionMethod($model, $name);
            $frameworkPath = dirname((new ReflectionClass(Model::class))->getFileName()).DIRECTORY_SEPARATOR;
            $types = $method->getReturnType();
            $types = $types instanceof ReflectionUnionType ? $types->getTypes() : ($types ? [$types] : []);
            $nonRelationTypes = array_filter($types, fn ($type) => ! $type instanceof ReflectionNamedType ||
                ($type->getName() !== 'null' && ! is_a($type->getName(), Relation::class, true)));

            // ORM traits appear to be declared on the consuming model, too.
            // Reject their source files before invoking any lifecycle methods.
            if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0 ||
                ! $method->getDeclaringClass()->isSubclassOf(Model::class) ||
                str_starts_with($method->getFileName() ?: '', $frameworkPath) || $nonRelationTypes !== []) {
                throw new AdvancedSearchException("Invalid relationship: {$name}");
            }
            $relation = Relation::noConstraints(fn () => $model->{$name}());
        } elseif ($resolver = $model->relationResolver($model::class, $name)) {
            $relation = Relation::noConstraints(fn () => $resolver($model));
        } else {
            throw new AdvancedSearchException("Unknown relationship: {$name}");
        }

        if (! $relation instanceof Relation) {
            throw new AdvancedSearchException("Invalid relationship: {$name}");
        }

        return $relation;
    }
}
