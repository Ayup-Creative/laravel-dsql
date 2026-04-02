<?php

namespace AyupCreative\AdvancedSearch\Registry;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Operators\Operator;

class OperatorRegistry
{
    protected array $operators = [];

    public function register(string $name, string|Operator $operator)
    {
        $this->operators[$name] = $operator;
    }

    public function getNames(): array
    {
        return array_keys($this->operators);
    }

    public function resolve(string $name): Operator
    {
        if (! isset($this->operators[$name])) {
            throw new AdvancedSearchException("Unknown operator: $name");
        }

        $operator = $this->operators[$name];

        if (is_string($operator)) {
            return new $operator;
        }

        return $operator;
    }
}
