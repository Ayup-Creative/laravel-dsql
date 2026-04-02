<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Exceptions\LexerException;
use AyupCreative\AdvancedSearch\Exceptions\ParserException;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class ValidateTest extends TestCase
{
    protected AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = app(AdvancedSearch::class);
    }

    public function test_it_validates_correct_query()
    {
        // Should not throw exception
        $this->search->validate('[name]:equals"Test"', Product::class);
        $this->search->validate('SELECT [name] WHERE [price]:gt 100 sort(price, desc) limit(10)', Product::class);
        $this->assertTrue(true);
    }

    public function test_it_throws_lexer_exception_for_invalid_tokens()
    {
        $this->expectException(LexerException::class);
        $this->search->validate('[name]:equals"Test', Product::class); // Missing closing quote
    }

    public function test_it_throws_parser_exception_for_invalid_syntax()
    {
        $this->expectException(ParserException::class);
        $this->search->validate('[name]:equals', Product::class); // Missing value
    }

    public function test_it_throws_exception_for_unknown_operator()
    {
        $this->expectException(AdvancedSearchException::class);
        $this->expectExceptionMessage('Unknown operator: unknown_op');
        $this->search->validate('[name]:unknown_op"Test"', Product::class);
    }

    public function test_it_throws_exception_for_unknown_dynamic_value()
    {
        $this->expectException(AdvancedSearchException::class);
        $this->expectExceptionMessage('Unknown dynamic value function: unknown_func');
        $this->search->validate('[created_at]:gt unknown_func()', Product::class);
    }

    public function test_it_validates_complex_arithmetic_and_aliases()
    {
        $query = 'SELECT [price] * 1.2 AS "vat", [vat] * 0.5 AS "half_vat" WHERE [vat]:gt 100';
        $this->search->validate($query, Product::class);
        $this->assertTrue(true);
    }

    public function test_it_validates_casts()
    {
        $query = 'SELECT CAST([price], "money") AS "formatted"';
        $this->search->validate($query, Product::class);
        $this->assertTrue(true);
    }
}
