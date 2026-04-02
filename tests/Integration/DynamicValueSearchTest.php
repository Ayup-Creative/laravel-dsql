<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Support\Carbon;

class DynamicValueSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use a fixed date for stability
        Carbon::setTestNow('2026-03-15 12:00:00');

        Product::create(['name' => 'Old Product', 'price' => 10, 'status' => 'active', 'created_at' => Carbon::now()->subMonths(2)]);
        Product::create(['name' => 'Recent Product', 'price' => 20, 'status' => 'active', 'created_at' => Carbon::now()->subDays(2)]);
        Product::create(['name' => 'New Product', 'price' => 30, 'status' => 'active', 'created_at' => Carbon::now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_filters_using_now()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // Products created in the last 7 days
        $search->apply($query, '[created_at]:gt now()->subDays(7)');

        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertEquals('Recent Product', $results[0]->name);
        $this->assertEquals('New Product', $results[1]->name);
    }

    public function test_it_filters_using_between_with_dynamic_values()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // Products created this month (assuming today is middle of the month)
        // Actually let's use subMonths to be sure
        $search->apply($query, '[created_at]:between(now()->subMonths(1), now())');

        $results = $query->get();

        $this->assertCount(2, $results);
    }

    public function test_it_filters_using_today()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        $search->apply($query, '[created_at]:gt today()');

        $results = $query->get();

        // Only "New Product" was created today (Carbon::now() is today)
        // Wait, Carbon::today() is midnight. Carbon::now() is later. So gt today() should match.
        $this->assertCount(1, $results);
        $this->assertEquals('New Product', $results[0]->name);
    }

    public function test_it_filters_using_complex_chain_from_issue_description()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // This should not throw and should compile correctly
        $search->apply($query, '[created_at]:between(now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay())');

        $results = $query->get();
        // Since all products in setUp are within this month (mostly), we expect results.
        // Old Product is 2 months old, so it shouldn't match.
        // Recent Product is 2 days old, it should match.
        // New Product is now, it should match.
        $this->assertCount(2, $results);
    }
}
