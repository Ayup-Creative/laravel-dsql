<?php

namespace AyupCreative\AdvancedSearch;

use AyupCreative\AdvancedSearch\Console\Commands\MakeSearchOperator;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use AyupCreative\AdvancedSearch\Registry\DynamicValueRegistry;
use AyupCreative\AdvancedSearch\Registry\OperatorRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AdvancedSearchServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ColumnRegistry::class, fn () => new ColumnRegistry);
        $this->app->singleton(Registry\CastRegistry::class, fn () => new Registry\CastRegistry);
        $this->app->singleton(OperatorRegistry::class, function () {
            $registry = new OperatorRegistry;
            $registry->register('equals', Operators\EqualsOperator::class);
            $registry->register('in', Operators\InOperator::class);
            $registry->register('gt', Operators\GtOperator::class);
            $registry->register('lt', Operators\LtOperator::class);
            $registry->register('between', Operators\BetweenOperator::class);
            $registry->register('contains', Operators\ContainsOperator::class);

            return $registry;
        });

        $this->app->singleton(DynamicValueRegistry::class, function () {
            $registry = new DynamicValueRegistry;
            $registry->register('now', fn () => Carbon::now());
            $registry->register('today', fn () => Carbon::today());
            $registry->register('tomorrow', fn () => Carbon::tomorrow());
            $registry->register('yesterday', fn () => Carbon::yesterday());

            return $registry;
        });

        $this->app->singleton(AdvancedSearch::class, function ($app) {
            return new AdvancedSearch(
                $app->make(ColumnRegistry::class),
                $app->make(OperatorRegistry::class),
                $app->make(DynamicValueRegistry::class),
                $app->make(Registry\CastRegistry::class)
            );
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSearchOperator::class,
            ]);
        }
    }
}
