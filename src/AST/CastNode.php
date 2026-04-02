<?php

namespace AyupCreative\AdvancedSearch\AST;

class CastNode extends Node
{
    public function __construct(
        public Node $expression,
        public string $type
    ) {}

    public function __toString(): string
    {
        return "CAST({$this->expression}, '{$this->type}')";
    }
}
