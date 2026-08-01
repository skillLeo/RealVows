<?php

namespace App\Console\Commands;

use App\Actions\RecordAuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    /**
     * The only way an administrator is ever created.
     *
     * There is deliberately no public route, no invite link, and no
     * self-service escalation — an admin can approve verifications and see
     * every member's photographs, so the ability to mint one stays on the
     * server behind SSH.
     */
    protected $signature = 'realvows:create-admin
                            {--name= : Display name}
                            {--email= : Email address}
                            {--password= : Password. Prefer the interactive prompt — an option lands in shell history}
                            {--role=super_admin : super_admin or admin}';

    protected $description = 'Create an administrator account';

    public function handle(RecordAuditLog $audit): int
    {
        $role = $this->option('role');

        if (! in_array($role, ['super_admin', 'admin'], true)) {
            $this->error('Role must be super_admin or admin.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text('Display name', required: true);
        $email = $this->option('email') ?: text('Email address', required: true);

        // Interactively (the normal case, over SSH) the password is typed at
        // a hidden prompt and never touches the shell history. Prompts cannot
        // read piped input, so a scripted deploy passes --password instead.
        $plain = $this->option('password');

        if (! $plain) {
            if (! $this->input->isInteractive()) {
                $this->error('Run this interactively, or pass --password for an unattended deploy.');

                return self::FAILURE;
            }

            $plain = password('Password (minimum 12 characters)', required: true);
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $plain],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::min(12)->letters()->numbers()->uncompromised()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plain),
        ]);

        // An account made from the server console is trusted by definition;
        // sending it through the email-verification flow would only lock the
        // first administrator out of the system they were just given.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($role);

        $audit->handle('admin.created', $user, ['role' => $role], $user);

        $this->newLine();
        $this->info("Created {$role}: {$email}");

        return self::SUCCESS;
    }
}
