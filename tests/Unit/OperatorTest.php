<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Operators\BetweenOperator;
use AyupCreative\AdvancedSearch\Operators\EqualsOperator;
use AyupCreative\AdvancedSearch\Registry\OperatorRegistry;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PHPUnit\Framework\TestCase;

class OperatorTest extends TestCase
{
    public function test_between_operator_throws_on_wrong_value_count()
    {
        $this->expectException(AdvancedSearchException::class);
        $op = new BetweenOperator;
        $query = Mockery::mock(Builder::class);
        $op->apply($query, fn () => null, ['type' => 'list', 'value' => [1]]);
    }

    public function test_operator_registry_can_resolve_from_class_name()
    {
        $registry = new OperatorRegistry;
        $registry->register('eq', EqualsOperator::class);
        $op = $registry->resolve('eq');
        $this->assertInstanceOf(EqualsOperator::class, $op);
    }

    public function test_operator_registry_can_resolve_from_instance()
    {
        $registry = new OperatorRegistry;
        $registry->register('eq', new EqualsOperator);
        $op = $registry->resolve('eq');
        $this->assertInstanceOf(EqualsOperator::class, $op);
    }

    public function test_operator_registry_throws_on_unknown()
    {
        $this->expectException(AdvancedSearchException::class);
        $registry = new OperatorRegistry;
        $registry->resolve('unknown');
    }
}
