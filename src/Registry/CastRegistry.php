<?php

namespace AyupCreative\AdvancedSearch\Registry;

use Illuminate\Database\Eloquent\Model;

class CastRegistry
{
    /**
     * @var array<string, callable>
     */
    protected array $casts = [];

    /**
     * Register a new cast.
     */
    public function register(string $name, callable $callback): void
    {
        $this->casts[$name] = $callback;
    }

    /**
     * Get a cast by name.
     */
    public function get(string $name): ?callable
    {
        return $this->casts[$name] ?? null;
    }

    /**
     * Apply a cast to a value.
     */
    public function cast(string $name, mixed $value, Model $model): mixed
    {
        $callback = $this->get($name);
        if ($callback) {
            return $callback($value, $model);
        }

        return $value;
    }

    /**
     * Get all registered cast names.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->casts);
    }
}
