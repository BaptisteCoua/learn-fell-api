<?php

namespace Functional\Users\Console;

use Functional\Users\Models\User;
use Illuminate\Console\Command;

class GrantAdminCommand extends Command
{
    protected $signature = 'users:grant-admin {email : Email of an existing account}';

    protected $description = 'Give the admin role to an existing account';

    public function handle(): int
    {
        $user = User::query()->where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if ($user === null) {
            $this->components->error('No account uses this email.');

            return self::FAILURE;
        }

        $user->assignRole('admin');
        $this->components->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
