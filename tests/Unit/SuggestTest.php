<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class SuggestTest extends TestCase
{
    protected AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = $this->app->make(AdvancedSearch::class);
    }

    public function test_suggest_starts_with_bracket_or_select()
    {
        $suggestions = $this->search->suggest('', Product::class);
        $this->assertContains('[', $suggestions);
        $this->assertContains('SELECT', $suggestions);
    }

    public function test_suggest_columns_after_select()
    {
        $suggestions = $this->search->suggest('SELECT ', Product::class);
        $this->assertContains('[', $suggestions);
    }

    public function test_suggest_as_where_comma_after_select_column()
    {
        $suggestions = $this->search->suggest('SELECT [name]', Product::class);
        $this->assertContains('AS', $suggestions);
        $this->assertContains(',', $suggestions);
        $this->assertContains('WHERE', $suggestions);
    }

    public function test_suggest_alias_start_after_as()
    {
        $suggestions = $this->search->suggest('SELECT [name] AS ', Product::class);
        $this->assertContains('"', $suggestions);
        $this->assertContains('[', $suggestions);
    }

    public function test_suggest_columns_when_typing_bracket()
    {
        $suggestions = $this->search->suggest('[', Product::class);
        $this->assertContains('status', $suggestions);
        $this->assertContains('name', $suggestions);
    }

    public function test_suggest_filtered_columns()
    {
        $suggestions = $this->search->suggest('[st', Product::class);
        $this->assertContains('status', $suggestions);
        $this->assertNotContains('name', $suggestions);
    }

    public function test_suggest_colon_after_bracket_closed()
    {
        $suggestions = $this->search->suggest('[status]', Product::class);
        $this->assertContains(':', $suggestions);
    }

    public function test_suggest_operators_after_colon()
    {
        $suggestions = $this->search->suggest('[status]:', Product::class);
        $this->assertContains('equals', $suggestions);
        $this->assertContains('in', $suggestions);
    }

    public function test_suggest_filtered_operators()
    {
        $suggestions = $this->search->suggest('[status]:eq', Product::class);
        $this->assertContains('equals', $suggestions);
        $this->assertNotContains('in', $suggestions);
    }

    public function test_suggest_value_start_after_operator()
    {
        $suggestions = $this->search->suggest('[status]:equals', Product::class);
        $this->assertContains('"', $suggestions);
        $this->assertContains('[', $suggestions);

        $suggestionsIn = $this->search->suggest('[status]:in', Product::class);
        $this->assertContains('(', $suggestionsIn);
    }

    public function test_suggest_nothing_inside_quotes()
    {
        $suggestions = $this->search->suggest('[status]:equals"', Product::class);
        $this->assertEmpty($suggestions);

        $suggestions2 = $this->search->suggest('[status]:equals"active', Product::class);
        $this->assertEmpty($suggestions2);
    }

    public function test_suggest_logical_operators_after_expression()
    {
        $suggestions = $this->search->suggest('[status]:equals"active" ', Product::class);
        $this->assertContains('AND', $suggestions);
        $this->assertContains('OR', $suggestions);
        $this->assertContains('sort(', $suggestions);
        $this->assertContains('limit(', $suggestions);
    }

    public function test_suggest_sort_columns()
    {
        $suggestions = $this->search->suggest('[status]:equals"active" sort(', Product::class);
        $this->assertContains('status', $suggestions);
        $this->assertContains('price', $suggestions);
    }

    public function test_suggest_sort_direction()
    {
        $suggestions = $this->search->suggest('[status]:equals"active" sort(price, ', Product::class);
        $this->assertContains('asc', $suggestions);
        $this->assertContains('desc', $suggestions);
    }

    public function test_suggest_keywords_when_typing()
    {
        $suggestions = $this->search->suggest('[status]:equals"active" l', Product::class);
        $this->assertContains('limit(', $suggestions);
        $this->assertNotContains('AND', $suggestions);

        $suggestions2 = $this->search->suggest('[status]:equals"active" A', Product::class);
        $this->assertContains('AND', $suggestions2);
        $this->assertNotContains('limit(', $suggestions2);
    }

    public function test_suggest_cast_options()
    {
        $suggestions = $this->search->suggest('SELECT ', Product::class);
        $this->assertContains('CAST(', $suggestions);

        $suggestions2 = $this->search->suggest('SELECT CAST(', Product::class);
        $this->assertContains('[', $suggestions2);

        // Register a cast
        $this->search->casts()->register('money', fn () => '');

        $suggestions3 = $this->search->suggest('SELECT CAST([price], ', Product::class);
        $this->assertContains('"money"', $suggestions3);
    }
}
