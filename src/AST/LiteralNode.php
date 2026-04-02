<?php

namespace AyupCreative\AdvancedSearch\AST;

class LiteralNode extends Node
{
    public function __construct(public mixed $value) {}

    public function __toString(): string
    {
        if (is_string($this->value)) {
            return '"'.addcslashes($this->value, '"\\').'"';
        }

        if (is_array($this->value)) {
            return '('.implode(', ', array_map(function ($v) {
                if (is_string($v)) {
                    return '"'.addcslashes($v, '"\\').'"';
                }

                return (string) $v;
            }, $this->value)).')';
        }

        return (string) $this->value;
    }
}
