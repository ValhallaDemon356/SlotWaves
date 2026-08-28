<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Airport;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create reference home airport (e.g. CGK / WIII)
        $homeAirport = Airport::firstOrCreate(
            ['iata_code' => 'CGK'],
            [
                'name' => 'Soekarno-Hatta International Airport',
                'icao_code' => 'WIII',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'country_code' => 'ID',
                'timezone' => 'Asia/Jakarta',
                'utc_offset_minutes' => 420,
                'is_home_airport' => true,
            ]
        );

        // Create Admin
        User::updateOrCreate(
            ['email' => 'admin@slotwaves.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'home_airport_id' => $homeAirport->id,
                'department' => 'Airport Administration',
                'phone' => '+62811111111',
                'is_active' => true,
            ]
        );

        // Create Slot Coordinator
        User::updateOrCreate(
            ['email' => 'coordinator@slotwaves.com'],
            [
                'name' => 'Slot Coordinator',
                'password' => Hash::make('password'),
                'role' => UserRole::SlotCoordinator,
                'home_airport_id' => $homeAirport->id,
                'department' => 'Slot Planning Office',
                'phone' => '+62822222222',
                'is_active' => true,
            ]
        );

        // Create Airport Operator
        User::updateOrCreate(
            ['email' => 'operator@slotwaves.com'],
            [
                'name' => 'Airport Operator',
                'password' => Hash::make('password'),
                'role' => UserRole::AirportOperator,
                'home_airport_id' => $homeAirport->id,
                'department' => 'Aviation Operations Center',
                'phone' => '+62833333333',
                'is_active' => true,
            ]
        );
    }
}
