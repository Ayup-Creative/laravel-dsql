<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Tests\Concerns\CreatesCompilationFixtures;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationCategory;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationProduct;
use AyupCreative\AdvancedSearch\Tests\Models\ProductLog;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

class QueryCompilationEdgeTest extends TestCase
{
    use CreatesCompilationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCompilationFixtures();
    }

    #[DataProvider('selfRelationQueries')]
    public function test_it_resolves_self_relations_with_scopes_and_aliases(string $input): void
    {
        $this->asento->update(['stock' => $this->other->id]);
        $query = $this->search->apply(CompilationProduct::query(), $input);
        $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
    }

    public static function selfRelationQueries(): array
    {
        return [
            ['[parentProduct.price]:equals 200'],
            ['[parentProduct.price] * 2 :gt 300'],
            ['[parentProduct.price]:gt[price]'],
            ['sort(parentProduct.price, desc) limit(1)'],
            ['EXISTS([parentProduct]):equals true'],
        ];
    }

    public function test_it_honors_related_global_scopes_for_filters_scalar_expressions_and_counts(): void
    {
        $scopes = CompilationCategory::getAllGlobalScopes();
        CompilationCategory::addGlobalScope('visible', fn (Builder $query) => $query->where('name', 'Other brand'));
        try {
            foreach (['[category.name]:equals"Asento"', 'COUNT([category]):equals 1 AND [category.name]:equals"Asento"', '[category.id] * 1 :equals 1'] as $input) {
                $query = $this->search->apply(CompilationProduct::query(), $input);
                $this->assertSame([], $query->pluck('products.id')->all());
            }
        } finally {
            CompilationCategory::setAllGlobalScopes($scopes);
        }
    }

    public function test_it_handles_plural_matches_once_and_nested_aggregates_with_no_matches(): void
    {
        $query = $this->search->apply(CompilationProduct::query(), '[logs.message]:contains"Purchased"');
        $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
        $query = $this->search->apply(CompilationProduct::query(), 'SELECT [id], COUNT([logs.detail]) AS "Total", EXISTS([logs.detail]) AS "Found" sort(id, asc)');
        $this->assertSame([0, 0], $query->get()->pluck('Total')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([0, 0], $query->get()->pluck('Found')->map(fn ($value) => (int) $value)->all());
    }

    public function test_it_preserves_scoped_relationship_bindings_in_select_where_and_order(): void
    {
        ProductLog::create(['product_id' => $this->asento->id, 'message' => 'Ignored']);
        $input = 'SELECT [id], COUNT([purchasedLogs]) AS "Total" WHERE [Total] * 10 :between(15, 25) sort(Total, desc)';
        $query = $this->search->apply(CompilationProduct::query(), $input);
        $this->assertSame(['Purchased%', 'Purchased%', 10, 15, 25, 'Purchased%'], $query->getBindings());
        $results = $query->get();
        $this->assertSame([$this->asento->id], $results->pluck('id')->all());
        $this->assertEquals($this->asento->purchasedLogs()->count(), $results->first()->Total);
    }

    public function test_it_supports_registered_dynamic_relationships_and_selects_their_keys(): void
    {
        CompilationProduct::resolveRelationUsing('brandForQuery', fn (CompilationProduct $product) => $product->belongsTo(CompilationCategory::class, 'category_id'));
        $input = 'SELECT [brandForQuery.name] AS "Brand" WHERE [brandForQuery.label]:equals"Asento"';
        $result = $this->search->apply(CompilationProduct::query(), $input)->firstOrFail();
        $selection = $this->search->getSelections($input, CompilationProduct::class)[0];
        $this->assertSame('Asento', $result->getSelectionValue('Brand', $selection));
    }

    public function test_it_resolves_aliases_on_the_right_of_custom_column_resolvers(): void
    {
        $this->search->columns()->register('cost', fn ($query, $op, $value) => $query->whereColumn('price', substr($op, 0, -7), $value), [], CompilationProduct::class);
        $input = 'SELECT [id], [stock] * 1 AS "Threshold" WHERE [cost]:gt[Threshold]';
        $query = $this->search->apply(CompilationProduct::query(), $input);
        $this->assertStringNotContainsString('> "Threshold"', $query->toSql());
        $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
    }

    public function test_it_leaves_the_original_builder_unchanged_when_compilation_is_rejected(): void
    {
        $query = CompilationProduct::whereKey($this->asento->id);
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        try {
            $this->search->apply($query, 'SELECT [category.name] AS "Brand" WHERE [missing]:equals 1');
            $this->fail('The invalid field must be rejected.');
        } catch (AdvancedSearchException) {
            $this->assertSame($sql, $query->toSql());
            $this->assertSame($bindings, $query->getBindings());
            $this->assertSame([], $query->getEagerLoads());
        }
    }

    public function test_it_preserves_nested_registered_expression_aliases(): void
    {
        $this->search->columns()->registerExpression('brand_name', '[category.label]', [], CompilationProduct::class);
        $this->search->columns()->registerExpression('brand_copy', '[brand_name]', [], CompilationProduct::class);
        $query = $this->search->apply(CompilationProduct::query(), '[brand_copy]:equals"Asento"');
        $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
    }

    public function test_it_rejects_registered_expression_cycles_and_unknown_selected_fields(): void
    {
        $this->search->columns()->registerExpression('cycle_a', '[cycle_b]', [], CompilationProduct::class);
        $this->search->columns()->registerExpression('cycle_b', '[cycle_a]', [], CompilationProduct::class);
        $this->expectException(AdvancedSearchException::class);
        $this->search->apply(CompilationProduct::query(), '[cycle_a]:equals 1');
    }

    public function test_it_preserves_trusted_native_sql_expressions_in_predicates_selections_and_sorting(): void
    {
        $this->search->columns()->registerExpression('lower_name', 'LOWER(name)', [], CompilationProduct::class);
        $this->search->columns()->registerExpression('native_net', 'price * 0.8', [], CompilationProduct::class);
        $input = 'SELECT [id], [native_net] AS "Value" WHERE [lower_name]:equals"other-200" AND [native_net]:equals 160 sort(lower_name, desc)';
        $query = $this->search->apply(CompilationProduct::query(), $input);
        $results = $query->get();
        $this->assertSame([$this->other->id], $results->pluck('id')->all());
        $this->assertEquals(160, $results->first()->Value);
    }

    public function test_it_supports_signed_numeric_values_and_arithmetic(): void
    {
        $query = $this->search->apply(CompilationProduct::query(), '[price]:between(-150, 150) AND -[price] :lt -50');
        $this->assertSame([$this->asento->id], $query->pluck('products.id')->all());
    }

    public function test_it_applies_zero_limits_without_dropping_them(): void
    {
        $query = $this->search->apply(CompilationProduct::query(), 'sort(price, asc) limit(0)');
        $this->assertSame([], $query->get()->all());
    }

    public function test_it_does_not_treat_an_implicit_cast_alias_as_a_cycle(): void
    {
        $query = $this->search->apply(CompilationProduct::query(), 'SELECT [id], CAST([price], "money") WHERE [price]:gt 150 sort(price, desc)');
        $this->assertSame([$this->other->id], $query->pluck('products.id')->all());
    }

    public function test_it_quotes_reserved_selection_aliases_as_identifiers(): void
    {
        $query = $this->search->apply(CompilationProduct::query(), 'SELECT [name] AS "Order", [price] AS "Group" WHERE [Group]:gt 150');
        $this->assertSame(['OTHER-200'], $query->get()->pluck('Order')->all());
    }

    public function test_it_rejects_nonliteral_dynamic_values_before_producing_a_query(): void
    {
        $this->search->dynamicValues()->register('invalidLiteral', fn () => ['not', 'a', 'scalar']);
        $this->expectException(AdvancedSearchException::class);
        $this->search->validate('[price]:equals invalidLiteral()', CompilationProduct::class);
    }
}
