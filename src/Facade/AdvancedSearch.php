<?php

namespace AyupCreative\AdvancedSearch\Facade;

use Illuminate\Support\Facades\Facade;

class AdvancedSearch extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \AyupCreative\AdvancedSearch\AdvancedSearch::class;
    }
}
