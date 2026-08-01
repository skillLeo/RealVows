<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class SeedDemoData extends Command
{
    protected $signature = 'realvows:demo {--force : Run outside local. You almost certainly do not want this}';

    protected $description = 'Populate the database with demonstration members, photos and matches';

    public function handle(): int
    {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->error('Refusing to run outside local.');
            $this->line('Every demo account shares one published password. On a live');
            $this->line('server that is a way in for anybody who reads this repository.');

            return self::FAILURE;
        }

        $this->info('Building demo data. Photos run through the real upload pipeline, so this takes a minute.');

        $this->callSilent('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
        $this->callSilent('db:seed', ['--class' => 'SettingsSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

        $this->newLine();
        $this->line('  <options=bold>Sign in with</> — password for every account: <fg=yellow>'.DemoSeeder::PASSWORD.'</>');
        $this->line('    admin@realvows.test      super admin');
        $this->line('    reviewer@realvows.test   verification reviewer');
        $this->line('    boy1@realvows.test  … boy15@realvows.test');
        $this->line('    girl1@realvows.test … girl15@realvows.test');
        $this->newLine();
        $this->line('  Set <fg=yellow>REALVOWS_DEMO_LOGINS=true</> in .env for one-click buttons on the login page.');

        return self::SUCCESS;
    }
}
