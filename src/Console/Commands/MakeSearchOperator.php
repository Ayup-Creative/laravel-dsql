<?php

namespace AyupCreative\AdvancedSearch\Console\Commands;

use Illuminate\Console\Command;

class MakeSearchOperator extends Command
{
    protected $signature = 'make:search-operator {name}';

    protected $description = 'Create a new search operator';

    public function handle()
    {
        $name = $this->argument('name');

        $this->info("Operator {$name} created (stub example).");
    }
}
