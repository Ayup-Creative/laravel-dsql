<?php

namespace AyupCreative\AdvancedSearch\Compiler;

final readonly class CompiledExpression
{
    public function __construct(public string $sql, public array $bindings = []) {}
}
