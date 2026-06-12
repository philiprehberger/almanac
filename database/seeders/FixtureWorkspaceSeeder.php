<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FixtureWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        \Artisan::call('almanac:seed-fixture-workspace');
        echo \Artisan::output();
    }
}
