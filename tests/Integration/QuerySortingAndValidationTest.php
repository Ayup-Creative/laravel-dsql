<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Tests\Concerns\CreatesCompilationFixtures;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationProduct;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

class QuerySortingAndValidationTest extends TestCase
{
    use CreatesCompilationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCompilationFixtures();
    }

    #[DataProvider('sorts')]
    public function test_it_sorts_and_limits_the_compiled_value(string $input, array $names): void
    {
        $query = $this->search->apply(CompilationProduct::query(), $input);
        $this->assertSame($names, $query->get()->pluck('name')->all());
    }

    public static function sorts(): array
    {
        return [
            'physical' => ['sort(price, desc) limit(1)', ['OTHER-200']],
            'singular relationship' => ['sort(category.name, desc)', ['OTHER-200', 'AS-100']],
            'related virtual' => ['sort(category.label, desc)', ['OTHER-200', 'AS-100']],
            'arithmetic alias' => ['SELECT [name], [price] * 0.8 AS "Net" sort(Net, desc)', ['OTHER-200', 'AS-100']],
            'relationship alias' => ['SELECT [name], [category.name] AS "Brand" sort(Brand, desc)', ['OTHER-200', 'AS-100']],
            'aggregate alias' => ['SELECT [name], COUNT([logs]) AS "Count" sort(Count, desc)', ['AS-100', 'OTHER-200']],
            'zero limit' => ['limit(0)', []],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_queries_are_rejected_by_validate_and_apply_without_mutation(string $input): void
    {
        foreach (['validate', 'apply'] as $method) {
            $connection = (new CompilationProduct)->getConnection();
            $connection->enableQueryLog();
            $connection->flushQueryLog();
            try {
                if ($method === 'validate') {
                    $this->search->validate($input, CompilationProduct::class);
                } else {
                    $this->search->apply(CompilationProduct::query(), $input);
                }
                $this->fail("{$method} accepted an invalid query: {$input}");
            } catch (AdvancedSearchException $error) {
                $this->assertNotSame('', $error->getMessage());
            }
            $writes = array_filter($connection->getQueryLog(), fn ($entry) => preg_match('/^\s*(insert|update|delete|truncate)\b/i', $entry['query']));
            $connection->disableQueryLog();
            $this->assertSame([], array_values($writes), 'Rejected DSQL must not execute a write query.');
            $this->assertSame(2, CompilationProduct::withTrashed()->count());
            $this->assertSame(2, CompilationProduct::count());
        }
    }

    public static function invalidInputs(): array
    {
        return [
            'unknown relation' => ['[missing.name]:equals"Asento"'],
            'query method' => ['[truncate.name]:equals"Asento"'],
            'nested query method' => ['[category.truncate.name]:equals"Asento"'],
            'restore trait method' => ['[restore.name]:equals 1'],
            'quiet restore trait method' => ['[restoreQuietly.name]:equals 1'],
            'force delete trait method' => ['[forceDelete.name]:equals 1'],
            'nested restore trait method' => ['[category.restore.name]:equals 1'],
            'model method' => ['[save.name]:equals"Asento"'],
            'declared nonrelation' => ['[dangerous.name]:equals"Asento"'],
            'unknown selected related field' => ['SELECT [category.missing]'],
            'unknown field' => ['[missing]:equals 1'],
            'bare field predicate' => ['[price]'],
            'literal predicate' => ['42'],
            'wrong aggregate argument' => ['COUNT([price] + 1):gt 0'],
            'unknown aggregate relation' => ['EXISTS([missing])'],
            'range arity' => ['COUNT([logs]):between(1)'],
            'comparison list' => ['[price]:gt(1, 2)'],
            'comparison null' => ['[price]:gt null'],
            'contains list' => ['[name]:contains("A", "B")'],
            'column list' => ['[price]:in([stock])'],
            'plural sort' => ['sort(logs.message, asc)'],
            'plural arithmetic' => ['[logs.id] + 1 :gt 2'],
            'alias cycle' => ['SELECT [B] AS "A", [A] AS "B" WHERE [A]:gt 0'],
            'fractional limit' => ['limit(1.5)'],
        ];
    }
}
