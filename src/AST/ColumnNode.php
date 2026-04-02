<?php

namespace AyupCreative\AdvancedSearch\AST;

class ColumnNode extends Node
{
    public function __construct(public string $name) {}

    public function __toString(): string
    {
        return "[{$this->name}]";
    }
}
