<?php

namespace AyupCreative\AdvancedSearch\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DefaultSelections
{
    public function __construct(
        public array $columns
    ) {}
}
