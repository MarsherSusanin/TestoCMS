<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreatePatTokenCommand extends Command
{
    protected $signature = 'cms:token:create
        {email : User email}
        {name : Token name}
        {--abilities= : Comma-separated abilities}';

    protected $description = 'Create a Sanctum personal access token with granular abilities';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $abilitiesRaw = (string) ($this->option('abilities') ?? '');
        $abilities = $abilitiesRaw === ''
            ? ['settings:read']
            : array_values(array_filter(array_map('trim', explode(',', $abilitiesRaw))));

        $token = $user->createToken((string) $this->argument('name'), $abilities)->plainTextToken;

        $this->line($token);

        return self::SUCCESS;
    }
}
