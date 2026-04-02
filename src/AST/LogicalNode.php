<?php

namespace AyupCreative\AdvancedSearch\AST;

class LogicalNode extends Node
{
    public function __construct(
        public string $boolean,
        public Node $left,
        public ?Node $right = null
    ) {}

    public function __toString(): string
    {
        if (! $this->right) {
            return "NOT ({$this->left})";
        }

        return "({$this->left} {$this->boolean} {$this->right})";
    }
}
