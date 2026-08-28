<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'airport.home_iata',
                'value' => 'CGK',
                'type' => 'string',
                'group' => 'airport',
                'label' => 'Home Airport IATA',
                'description' => 'The IATA code of the base airport running this instance.',
                'is_public' => true,
            ],
            [
                'key' => 'timeline.default_zoom',
                'value' => '100',
                'type' => 'integer',
                'group' => 'timeline',
                'label' => 'Default Timeline Zoom (%)',
                'description' => 'Default percentage zoom level for timeline render grid.',
                'is_public' => true,
            ],
            [
                'key' => 'parser.auto_reconcile',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'parser',
                'label' => 'Auto-Reconcile on Import',
                'description' => 'Automatically match flight turnaround IDs during import.',
                'is_public' => false,
            ],
            [
                'key' => 'theme.primary_color',
                'value' => '#0F172A',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'Primary Brand Color',
                'description' => 'Aviation dark brand theme primary background color.',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
