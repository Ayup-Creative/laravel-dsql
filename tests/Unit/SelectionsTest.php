<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class SelectionsTest extends TestCase
{
    private AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = app(AdvancedSearch::class);
    }

    public function test_it_returns_empty_for_no_selection()
    {
        $selections = $this->search->getSelections('[status]:equals"active"');
        $this->assertEmpty($selections);
    }

    public function test_it_returns_simple_selections()
    {
        $selections = $this->search->getSelections('SELECT [id], [status] WHERE [status]:equals"active"');

        $this->assertCount(2, $selections);

        $this->assertEquals('id', $selections[0]['name']);
        $this->assertEquals('id', $selections[0]['label']);
        $this->assertFalse($selections[0]['is_alias']);
        $this->assertEquals('[id]', $selections[0]['expression']);

        $this->assertEquals('status', $selections[1]['name']);
        $this->assertEquals('status', $selections[1]['label']);
        $this->assertFalse($selections[1]['is_alias']);
        $this->assertEquals('[status]', $selections[1]['expression']);
    }

    public function test_it_returns_aliased_selections()
    {
        $selections = $this->search->getSelections('SELECT [purchase_price] AS "price", [status] AS "current_status"');

        $this->assertCount(2, $selections);

        $this->assertEquals('price', $selections[0]['name']);
        $this->assertEquals('price', $selections[0]['label']);
        $this->assertTrue($selections[0]['is_alias']);
        $this->assertEquals('[purchase_price]', $selections[0]['expression']);

        $this->assertEquals('current_status', $selections[1]['name']);
        $this->assertEquals('current_status', $selections[1]['label']);
        $this->assertTrue($selections[1]['is_alias']);
        $this->assertEquals('[status]', $selections[1]['expression']);
    }

    public function test_it_returns_arithmetic_selections()
    {
        $selections = $this->search->getSelections('SELECT [amount] * 1.2 AS "vat_total"');

        $this->assertCount(1, $selections);

        $this->assertEquals('vat_total', $selections[0]['name']);
        $this->assertEquals('vat_total', $selections[0]['label']);
        $this->assertTrue($selections[0]['is_alias']);
        $this->assertEquals('([amount] * 1.2)', $selections[0]['expression']);
    }

    public function test_it_handles_no_alias_arithmetic()
    {
        $selections = $this->search->getSelections('SELECT [amount] * 1.2');

        $this->assertCount(1, $selections);
        $this->assertEquals('([amount] * 1.2)', $selections[0]['name']);
        $this->assertFalse($selections[0]['is_alias']);
    }
}
