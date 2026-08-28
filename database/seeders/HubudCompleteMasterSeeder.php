<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class HubudCompleteMasterSeeder extends Seeder
{
    public function run(): void
    {
        // First run standard core seeder (37 PT API + reference data)
        $this->call(HubudAirportSeeder::class);

        // Then run the full Hubud importer command
        Artisan::call('slotwaves:import-hubud-airports');
    }
}
