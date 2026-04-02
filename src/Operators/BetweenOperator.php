<?php

namespace AyupCreative\AdvancedSearch\Operators;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use Illuminate\Database\Eloquent\Builder;

class BetweenOperator implements Operator
{
    public function apply(Builder $query, callable $columnResolver, array $value)
    {
        if ($value['type'] !== 'list' || count($value['value']) !== 2) {
            throw new AdvancedSearchException('Between operator expects a list of exactly 2 values');
        }
        $columnResolver($query, 'between', $value['value']);
    }
}
