<?php

namespace AyupCreative\AdvancedSearch\Operators;

use Illuminate\Database\Eloquent\Builder;

class ContainsOperator implements Operator
{
    public function apply(Builder $query, callable $columnResolver, array $value)
    {
        $val = $value['value'];
        $columnResolver($query, 'like', '%'.$val.'%');
    }
}
