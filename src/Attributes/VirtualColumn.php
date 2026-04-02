<?php

namespace AyupCreative\AdvancedSearch\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class VirtualColumn
{
    public function __construct(
        public string $name,
        public array $operators = [],
        public ?string $expression = null,
        public array $metadata = []
    ) {}
}
