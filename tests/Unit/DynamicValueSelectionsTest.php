<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class DynamicValueSelectionsTest extends TestCase
{
    public function test_it_returns_dynamic_values_in_selections()
    {
        $search = app(AdvancedSearch::class);
        $input = 'SELECT now() AS "time" WHERE [id]:gt 0';
        $selections = $search->getSelections($input);

        $this->assertCount(1, $selections);
        $this->assertEquals('time', $selections[0]['name']);
        $this->assertEquals('now()', $selections[0]['expression']);
    }

    public function test_it_returns_complex_dynamic_values_in_selections()
    {
        $search = app(AdvancedSearch::class);
        $input = 'SELECT now()->startOfMonth() AS "month_start"';
        $selections = $search->getSelections($input);

        $this->assertCount(1, $selections);
        $this->assertEquals('month_start', $selections[0]['name']);
        $this->assertEquals('now()->startOfMonth()', $selections[0]['expression']);
    }
}
