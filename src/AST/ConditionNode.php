<?php

namespace AyupCreative\AdvancedSearch\AST;

class ConditionNode extends Node
{
    public function __construct(
        public string|Node $column,
        public string $operator,
        public mixed $value
    ) {}

    public function __toString(): string
    {
        $column = $this->column instanceof Node ? (string) $this->column : "[{$this->column}]";
        $value = $this->value instanceof Node ? (string) $this->value : (new LiteralNode($this->value))->__toString();

        return "{$column}:{$this->operator}{$value}";
    }
}
