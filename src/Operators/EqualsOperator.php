<?php

namespace AyupCreative\AdvancedSearch\Operators;

use Illuminate\Database\Eloquent\Builder;

class EqualsOperator implements Operator
{
    public function apply(Builder $query, callable $columnResolver, array $value)
    {
        if ($value['type'] === 'column') {
            $columnResolver($query, '=_column', $value['value']);
        } elseif ($value['type'] === 'scalar') {
            $columnResolver($query, '=', $value['value']);
        } elseif ($value['type'] === 'list') {
            $columnResolver($query, 'in', $value['value']);
        }
    }
}
