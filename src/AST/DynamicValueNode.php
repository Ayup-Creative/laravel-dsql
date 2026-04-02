<?php

namespace AyupCreative\AdvancedSearch\AST;

class DynamicValueNode extends Node
{
    public function __construct(
        public string $name,
        public array $arguments = [],
        public ?DynamicValueNode $next = null
    ) {}

    public function __toString(): string
    {
        $args = implode(', ', array_map(function ($arg) {
            if ($arg instanceof Node) {
                return (string) $arg;
            }

            if (is_string($arg)) {
                return '"'.addcslashes($arg, '"\\').'"';
            }

            return (string) $arg;
        }, $this->arguments));

        $str = "{$this->name}($args)";

        if ($this->next) {
            $str .= "->{$this->next}";
        }

        return $str;
    }
}
