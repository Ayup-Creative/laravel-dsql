<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model implements Queryable
{
    use Searchable;

    protected $fillable = ['receipt_number', 'amount', 'purchased_at', 'registration_id'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
