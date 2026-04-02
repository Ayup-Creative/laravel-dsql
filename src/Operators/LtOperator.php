<?php

namespace AyupCreative\AdvancedSearch\Operators;

use Illuminate\Database\Eloquent\Builder;

class LtOperator implements Operator
{
    public function apply(Builder $query, callable $columnResolver, array $value)
    {
        $op = $value['type'] === 'column' ? '<_column' : '<';
        $columnResolver($query, $op, $value['value']);
    }
}
