<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * `php artisan jwt:secret` — episode 10.
 *
 * The signing key is the single most sensitive value in a JWT app: anyone who
 * has it can forge a token for any user, including a manager. Generating it
 * with a command means nobody is tempted to invent one by hand.
 */
class GenerateJwtSecret extends Command
{
    protected $signature = 'jwt:secret {--force : Overwrite an existing secret}';

    protected $description = 'Generate a random JWT signing secret and write it to .env';

    public function handle(): int
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            $this->components->error('No .env file found.');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        $secret = Str::random(64);

        $hasKey = preg_match('/^JWT_SECRET=.*$/m', $contents, $matches);

        if ($hasKey && trim(substr($matches[0], 11)) !== '' && ! $this->option('force')) {
            $this->components->warn('JWT_SECRET is already set. Use --force to replace it.');
            $this->components->bulletList([
                'Replacing the secret invalidates every access token already issued.',
                'Everyone signed in will be logged out at once.',
            ]);

            return self::FAILURE;
        }

        $contents = $hasKey
            ? preg_replace('/^JWT_SECRET=.*$/m', "JWT_SECRET={$secret}", $contents)
            : rtrim($contents, "\n")."\n\nJWT_SECRET={$secret}\n";

        file_put_contents($path, $contents);

        $this->components->info('JWT secret generated and written to .env');

        return self::SUCCESS;
    }
}
