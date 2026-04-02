<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class ApplianceModel extends Model
{
    protected $table = 'appliance_models';

    protected $fillable = ['name', 'standard_warranty_years'];
}
