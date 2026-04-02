<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class DynamicValueSuggestTest extends TestCase
{
    public function test_it_suggests_dynamic_values_after_operator()
    {
        $search = app(AdvancedSearch::class);
        $suggestions = $search->suggest('[price]:equals', Product::class);

        $this->assertContains('now()', $suggestions);
        $this->assertContains('today()', $suggestions);
    }

    public function test_it_suggests_dynamic_values_after_operator_and_space()
    {
        $search = app(AdvancedSearch::class);
        $suggestions = $search->suggest('[price]:equals ', Product::class);

        $this->assertContains('now()', $suggestions);
        $this->assertContains('today()', $suggestions);
    }

    public function test_it_filters_dynamic_value_suggestions()
    {
        $search = app(AdvancedSearch::class);
        // This regex doesn't currently handle filtering dynamic values, let's see.
        $suggestions = $search->suggest('[price]:equals n', Product::class);

        // Currently it would match case 7 and suggest AND, OR, etc.
        // We might need to improve this.
        $this->assertContains('now()', $suggestions);
    }
}
