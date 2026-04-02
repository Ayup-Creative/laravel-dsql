<?php

namespace AyupCreative\AdvancedSearch\Grammar;

class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $position
    ) {}
}
