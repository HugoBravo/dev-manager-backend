<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ExampleUserSeeder — seeds a single deterministic example user into
 * the configured database. Use this when you need a stable login
 * account without dragging in the full demo project (boards, columns,
 * cards, etc.).
 *
 * Run with:
 *   php artisan db:seed --class=ExampleUserSeeder
 *
 * Idempotent: re-running the seeder does not duplicate rows. The row is
 * keyed by a stable email; on each run the name, password, and email
 * verification timestamp are refreshed so the printed credentials stay
 * valid for development.
 *
 * Safe to run manually against any environment. It does not touch
 * DatabaseSeeder and only writes to the `users` table.
 */
final class ExampleUserSeeder extends Seeder
{
    private const EXAMPLE_EMAIL = 'example@dev-manager.test';

    private const EXAMPLE_PASSWORD = 'password';

    private const EXAMPLE_NAME = 'Example User';

    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::EXAMPLE_EMAIL],
            [
                'name' => self::EXAMPLE_NAME,
                'password' => Hash::make(self::EXAMPLE_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info('--- ExampleUserSeeder complete ---');
        $this->command?->info('Example user id: '.$user->id);
        $this->command?->info('Example user email: '.self::EXAMPLE_EMAIL);
        $this->command?->info('Example user password: '.self::EXAMPLE_PASSWORD);
    }
}
