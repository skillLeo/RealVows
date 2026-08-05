<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Which settings are filled in, without ever printing one.
 *
 * ⚠️ "IS IT CONFIGURED?" WAS ONLY ANSWERABLE BY OPENING THE ADMIN SCREEN.
 *
 * That is fine when the screen is right in front of you and useless when a
 * deploy has just run and somebody needs to know whether it took. Worse, the
 * screen masks secrets — so "••••••••3jIF" and an empty box look similar
 * enough at a glance to be misread, which is exactly how a missing credential
 * survives a check.
 *
 * ⚠️ IT PRINTS SET / EMPTY AND NOTHING ELSE. Never a value, not even a masked
 * one: a command whose output lands in a CI log is the wrong place for a
 * fragment of a key. The length is not shown either, since a length narrows a
 * guess.
 */
class ShowSettingsStatus extends Command
{
    protected $signature = 'realvows:settings-status {group? : Only this group}';

    protected $description = 'Show which settings are filled in, without revealing them';

    public function handle(): int
    {
        $query = Setting::query()->orderBy('group')->orderBy('sort_order');

        if ($group = $this->argument('group')) {
            $query->where('group', $group);
        }

        $settings = $query->get();

        if ($settings->isEmpty()) {
            $this->warn('No settings found. Has the seeder run?');

            return self::FAILURE;
        }

        $this->line('');

        foreach ($settings->groupBy('group') as $name => $rows) {
            $filled = $rows->filter(fn (Setting $s) => ($s->plainValue() ?? '') !== '')->count();

            $this->info(sprintf('── %s (%d of %d set) ──', $name, $filled, $rows->count()));

            foreach ($rows as $row) {
                $value = $row->plainValue() ?? '';
                $isSet = $value !== '';

                /*
                 * ⚠️ NON-SECRETS PRINT THEIR VALUE, SECRETS NEVER DO.
                 *
                 * A host name or a driver is not a credential, and hiding it
                 * makes the output useless for the thing it exists to answer —
                 * "is the driver still log?" is the question that matters most
                 * here, and it cannot be answered by "set".
                 */
                $shown = $row->is_secret
                    ? ($isSet ? 'set' : 'EMPTY')
                    : ($isSet ? $value : 'EMPTY');

                $this->line(sprintf(
                    '  %-24s %s%s',
                    $row->key,
                    $isSet ? '' : '⚠️  ',
                    $shown,
                ));
            }

            $this->line('');
        }

        return self::SUCCESS;
    }
}
