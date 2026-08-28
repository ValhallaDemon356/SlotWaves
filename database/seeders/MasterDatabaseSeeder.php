<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MasterDatabaseSeeder extends Seeder
{
    /**
     * Seed official master reference database (Hubud Kemenhub + Project Reference).
     */
    public function run(): void
    {
        $this->call([
            HubudAirportSeeder::class,
            HubudAirlineSeeder::class,
            HubudCompleteMasterSeeder::class,
        ]);
    }
}
