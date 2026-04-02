<?php

namespace AyupCreative\AdvancedSearch\Operators;

use Illuminate\Database\Eloquent\Builder;

interface Operator
{
    /**
     * @param  array{type: string, value: mixed}  $value
     */
    public function apply(Builder $query, callable $columnResolver, array $value);
}
