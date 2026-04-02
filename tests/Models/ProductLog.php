<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLog extends Model
{
    protected $guarded = [];

    public function detail()
    {
        return $this->belongsTo(ProductDetail::class);
    }
}
