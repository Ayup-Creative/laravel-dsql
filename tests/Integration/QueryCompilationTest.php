<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\Tests\Concerns\CreatesCompilationFixtures;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationDetail;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationProduct;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

class QueryCompilationTest extends TestCase
{
    use CreatesCompilationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCompilationFixtures();
    }

    #[DataProvider('predicates')]
    public function test_it_matches_eloquent_predicates(string $input, string $reference): void
    {
        $expected = match ($reference) {
            'asento' => CompilationProduct::whereKey($this->asento->id),
            'other' => CompilationProduct::whereKey($this->other->id),
            'both' => CompilationProduct::query(),
            'none' => CompilationProduct::whereRaw('0 = 1'),
        };
        $query = $this->search->apply(CompilationProduct::query(), $input);

        $this->assertSame($expected->orderBy('products.id')->pluck('products.id')->all(), $query->orderBy('products.id')->pluck('products.id')->all());
        $this->search->validate($input, CompilationProduct::class);
    }

    public static function predicates(): array
    {
        return [
            'relationship' => ['[category.name]:equals"Asento"', 'asento'],
            'nested relationship' => ['[detail.product.category.name]:equals"Asento"', 'asento'],
            'relationship selected' => ['SELECT [id], [category.name] AS "Brand" WHERE [category.name]:equals"Asento"', 'asento'],
            'selection alias' => ['SELECT [id], [category.name] AS "Brand" WHERE [Brand]:equals"Asento"', 'asento'],
            'cast alias' => ['SELECT [id], CAST([category.name], "text") AS "Brand" WHERE [Brand]:equals"Asento"', 'asento'],
            'related virtual expression' => ['[category.label]:equals"Asento"', 'asento'],
            'related custom resolver' => ['[category.normalized_name]:equals"ASENTO"', 'asento'],
            'related list' => ['[category.name]:in("Asento", "Missing")', 'asento'],
            'related equals list' => ['[category.name]:equals("Asento", "Missing")', 'asento'],
            'related contains' => ['[category.name]:contains"sent"', 'asento'],
            'related range' => ['[detail.product.price]:between(90, 110)', 'asento'],
            'related comparison' => ['[detail.product.price]:gt 150', 'other'],
            'empty list' => ['[category.name]:in()', 'none'],
            'OR' => ['[status]:equals"active" OR [price]:gt 150', 'both'],
            'AND' => ['[status]:equals"active" AND [price]:gt 150', 'none'],
            'precedence' => ['[price]:lt 150 OR [price]:gt 150 AND [status]:equals"inactive"', 'both'],
            'grouped OR' => ['([category.name]:equals"Asento" OR [price]:gt 150) AND [status]:equals"active"', 'asento'],
            'NOT group' => ['NOT ([category.name]:equals"Asento" OR [status]:equals"active")', 'other'],
            'count' => ['COUNT([logs]):gt 1', 'asento'],
            'count range' => ['COUNT([logs]):between(1, 3)', 'asento'],
            'count list' => ['COUNT([logs]):in(0, 3)', 'other'],
            'count arithmetic' => ['COUNT([logs]) * 10 :gt 10', 'asento'],
            'count alias' => ['SELECT [id], COUNT([logs]) AS "Count" WHERE [Count]:between(1, 3)', 'asento'],
            'exists' => ['EXISTS([logs])', 'asento'],
            'not exists' => ['NOT EXISTS([logs])', 'other'],
            'exists false' => ['EXISTS([logs]):equals false', 'other'],
            'exists zero' => ['EXISTS([logs]):equals 0', 'other'],
            'exists comparison' => ['EXISTS([logs]):lt 1', 'other'],
            'exists list' => ['EXISTS([logs]):in(0)', 'other'],
            'exists arithmetic' => ['EXISTS([logs]) + 1 :equals 1', 'other'],
            'column comparison' => ['[price]:gt[stock]', 'asento'],
            'related right column' => ['[price]:equals[detail.product.price]', 'both'],
            'related left column' => ['[detail.product.price]:gt[stock]', 'asento'],
            'alias right column' => ['SELECT [id], [price] * 0.8 AS "Net" WHERE [Net]:lt[stock]', 'other'],
            'registered expression' => ['[net]:lt[stock]', 'other'],
            'qualified physical field' => ['[products.price]:gt 150', 'other'],
            'relationship arithmetic' => ['[detail.product.price] * 2 :gt 300', 'other'],
            'nested count' => ['COUNT([detail.product.logs]):equals 2', 'asento'],
        ];
    }

    public function test_it_keeps_or_inside_existing_constraints_and_scopes(): void
    {
        $scopes = CompilationProduct::getAllGlobalScopes();
        CompilationProduct::addGlobalScope('owned', fn (Builder $query) => $query->whereKey($this->asento->id));
        try {
            $query = $this->search->apply(CompilationProduct::where('status', 'active'), '[category.name]:equals"Asento" OR [price]:gt 150');
            $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
        } finally {
            CompilationProduct::setAllGlobalScopes($scopes);
        }
    }

    public function test_it_selects_relationship_values_and_aggregates_while_filtering(): void
    {
        $input = 'SELECT [id], [category.name] AS "Brand", EXISTS([logs]) AS "Extended", COUNT([logs]) AS "Count" WHERE [category.name]:equals"Asento"';
        $results = $this->search->apply(CompilationProduct::query(), $input)->get();
        $this->assertCount(1, $results);
        $selection = collect($this->search->getSelections($input, CompilationProduct::class))->firstWhere('name', 'Brand');
        $this->assertSame('Asento', $results->first()->getSelectionValue('Brand', $selection));
        $this->assertEquals(1, $results->first()->Extended);
        $this->assertEquals(2, $results->first()->Count);
    }

    public function test_it_compiles_null_equality_and_excludes_missing_relations(): void
    {
        $this->other->update(['category_id' => null]);
        $query = $this->search->apply(CompilationProduct::query(), '[category_id]:equals null');
        $this->assertSame([$this->other->id], $query->pluck('products.id')->all());
        $query = $this->search->apply(CompilationProduct::query(), '[category.name]:equals"Asento"');
        $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
    }

    public function test_it_preserves_registered_dotted_resolver_priority(): void
    {
        $this->search->columns()->register('category.name', fn ($query, $op, $value) => $query->where('status', $op, $value), [], CompilationProduct::class);
        $query = $this->search->apply(CompilationProduct::query(), '[category.name]:equals"inactive"');
        $this->assertSame([$this->other->id], $query->pluck('products.id')->all());
    }

    public function test_it_respects_soft_deleted_relationships_in_predicates_and_subqueries(): void
    {
        $this->asento->category->delete();
        $query = $this->search->apply(CompilationProduct::query(), '[category.name]:equals"Asento"');
        $this->assertSame([], $query->pluck('products.id')->all());
        $query = $this->search->apply(CompilationDetail::query(), '[product.price] * 2 :gt 100');
        $this->asento->delete();
        $this->assertSame([$this->other->detail->id], $query->pluck('product_details.id')->all());
    }

    public function test_it_binds_values_and_expression_constants_in_order(): void
    {
        $value = "Asento' OR 1=1 --";
        $this->asento->category->update(['name' => $value]);
        $query = $this->search->apply(CompilationProduct::query(), '[category.name]:equals"'.$value.'"');
        $this->assertSame([$value], $query->getBindings());
        $this->assertStringNotContainsString($value, $query->toSql());
        $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
        $query = $this->search->apply(CompilationProduct::query(), 'COUNT([logs]) * 10 :gt 15');
        $this->assertSame([10, 15], $query->getBindings());
    }
}
