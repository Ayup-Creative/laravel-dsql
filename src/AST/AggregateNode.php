<?php

namespace AyupCreative\AdvancedSearch\AST;

class AggregateNode extends Node
{
    public function __construct(public string $function, public Node $expression) {}

    public function __toString(): string
    {
        return "{$this->function}({$this->expression})";
    }
}
