<?php

namespace AyupCreative\AdvancedSearch\Tests;

use AyupCreative\AdvancedSearch\AdvancedSearchServiceProvider;
use AyupCreative\AdvancedSearch\Facade\AdvancedSearch;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            AdvancedSearchServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'AdvancedSearch' => AdvancedSearch::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
