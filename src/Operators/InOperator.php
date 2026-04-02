<?php

namespace AyupCreative\AdvancedSearch\Operators;

use Illuminate\Database\Eloquent\Builder;

class InOperator implements Operator
{
    public function apply(Builder $query, callable $columnResolver, array $value)
    {
        $vals = $value['type'] === 'list' ? $value['value'] : [$value['value']];
        $columnResolver($query, 'in', $vals);
    }
}
