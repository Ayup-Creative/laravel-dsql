<?php

namespace AyupCreative\AdvancedSearch\Registry;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;

class DynamicValueRegistry
{
    protected array $functions = [];

    public function register(string $name, callable $callback): void
    {
        $this->functions[$name] = $callback;
    }

    public function resolve(string $name, array $args = []): mixed
    {
        if (! isset($this->functions[$name])) {
            throw new AdvancedSearchException("Unknown dynamic value function: $name");
        }

        return ($this->functions[$name])(...$args);
    }

    public function has(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    public function getNames(): array
    {
        return array_keys($this->functions);
    }
}
