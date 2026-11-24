<?php

namespace AyupCreative\AdvancedSearch\Tests\Concerns;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationCategory;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationDetail;
use AyupCreative\AdvancedSearch\Tests\Models\CompilationProduct;
use AyupCreative\AdvancedSearch\Tests\Models\ProductLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesCompilationFixtures
{
    protected AdvancedSearch $search;

    protected CompilationProduct $asento;

    protected CompilationProduct $other;

    protected function createCompilationFixtures(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('categories', fn (Blueprint $table) => $table->softDeletes());
        $this->search = app(AdvancedSearch::class);
        $this->asento = CompilationProduct::create([
            'name' => 'AS-100', 'price' => 100, 'stock' => 50, 'status' => 'active',
            'category_id' => CompilationCategory::create(['name' => 'Asento'])->id,
        ]);
        $this->other = CompilationProduct::create([
            'name' => 'OTHER-200', 'price' => 200, 'stock' => 250, 'status' => 'inactive',
            'category_id' => CompilationCategory::create(['name' => 'Other brand'])->id,
        ]);
        CompilationDetail::create(['product_id' => $this->asento->id, 'sku' => 'AS-100']);
        CompilationDetail::create(['product_id' => $this->other->id, 'sku' => 'OTHER-200']);
        ProductLog::create(['product_id' => $this->asento->id, 'message' => 'Purchased first']);
        ProductLog::create(['product_id' => $this->asento->id, 'message' => 'Purchased again']);
    }
}
