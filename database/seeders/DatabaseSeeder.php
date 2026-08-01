<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Reference data only — roles, permissions and the settings rows the
     * client edits in the browser. Safe to re-run on any environment,
     * including production.
     *
     * No member accounts are seeded here. The first administrator is created
     * deliberately, from the console, via `php artisan realvows:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
