<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class OperatorAutocompleteTest extends TestCase
{
    protected AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = $this->app->make(AdvancedSearch::class);
    }

    public function test_it_returns_all_operators()
    {
        $operators = $this->search->getAvailableOperators();

        $this->assertContains('equals', $operators);
        $this->assertContains('in', $operators);
        $this->assertContains('gt', $operators);
        $this->assertContains('lt', $operators);
        $this->assertContains('between', $operators);
        $this->assertContains('contains', $operators);
    }

    public function test_it_returns_specific_operators_for_column()
    {
        $this->search->columns()->register('limited_col', function () {}, ['equals', 'in']);

        $operators = $this->search->getAvailableOperators('limited_col');

        $this->assertEquals(['equals', 'in'], $operators);
    }

    public function test_it_falls_back_to_all_operators_if_none_specified_for_column()
    {
        $this->search->columns()->register('general_col', function () {});

        $operators = $this->search->getAvailableOperators('general_col');

        $this->assertContains('equals', $operators);
        $this->assertCount(6, $operators); // Default 6 operators
    }

    public function test_it_returns_operators_from_attributes()
    {
        $this->search->columns()->registerFromClass(TestModelWithOperators::class);

        $operators = $this->search->getAvailableOperators('status', TestModelWithOperators::class);

        $this->assertEquals(['equals', 'in'], $operators);
    }
}

class TestModelWithOperators
{
    #[VirtualColumn('status', operators: ['equals', 'in'])]
    public static function searchStatus($query, $op, $val) {}
}
