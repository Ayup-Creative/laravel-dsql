<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class AutocompleteTest extends TestCase
{
    protected AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = $this->app->make(AdvancedSearch::class);
    }

    public function test_it_returns_columns_for_autocomplete()
    {
        $columns = $this->search->getAutocomplete(Product::class);

        $this->assertContains('status', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('price', $columns);
        $this->assertContains('updated_at', $columns);
        $this->assertContains('category.name', $columns);
    }

    public function test_it_returns_blank_syntax()
    {
        $syntax = $this->search->getBlankSyntax('status');
        $this->assertEquals('[status]:equals""', $syntax);

        $syntaxWithOp = $this->search->getBlankSyntax('status', 'in');
        $this->assertEquals('[status]:in()', $syntaxWithOp);
    }
}
