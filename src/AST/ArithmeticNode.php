<?php

namespace AyupCreative\AdvancedSearch\AST;

class ArithmeticNode extends Node
{
    public function __construct(
        public string $operator,
        public Node $left,
        public Node $right
    ) {}

    public function __toString(): string
    {
        return "({$this->left} {$this->operator} {$this->right})";
    }
}
