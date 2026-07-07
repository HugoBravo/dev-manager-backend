<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Local + testing envs get the deterministic demo dataset (1 user,
     * 1 project, 1 board, 3 columns, 5 cards, 2 comments, 1 attachment)
     * so the dev client and the test suite share a stable fixture.
     * Production and other envs stay untouched — no demo data is
     * seeded.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoProjectSeeder::class);
        }
    }
}
