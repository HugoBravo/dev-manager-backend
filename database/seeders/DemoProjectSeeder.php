<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Card;
use App\Models\CardAttachment;
use App\Models\CardComment;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DemoProjectSeeder — Batch 7 — produces a deterministic demo dataset
 * for a fresh user. Used by:
 *   - `php artisan db:seed --class=DemoProjectSeeder`
 *   - `DatabaseSeeder::run()` when env is `local` or `testing`
 *
 * Idempotent: every find-or-create uses a stable unique key. Re-running
 * the seeder does not duplicate rows or tokens.
 *
 * At the end the seeder prints the demo user's Sanctum bearer token to
 * stdout via `$this->command->info()` so the developer can copy-paste
 * the token into the Angular client / curl headers without database
 * inspection.
 */
final class DemoProjectSeeder extends Seeder
{
    private const DEMO_EMAIL = 'demo@dev-manager.test';

    private const DEMO_PASSWORD = 'password';

    private const TOKEN_NAME = 'demo-token';

    public function run(): void
    {
        $user = $this->findOrCreateDemoUser();
        $project = $this->findOrCreateProject($user);
        $board = $this->findOrCreateBoard($project);
        $columns = $this->findOrCreateColumns($board);
        $cards = $this->findOrCreateCards($columns);
        $this->findOrCreateComments($cards, $user);
        $attachment = $this->findOrCreateAttachment($cards[0], $user);
        $token = $this->findOrCreateToken($user);

        $this->command?->info('--- DemoProjectSeeder complete ---');
        $this->command?->info('Demo user email: '.self::DEMO_EMAIL);
        $this->command?->info('Demo user password: '.self::DEMO_PASSWORD);
        $this->command?->info('Sanctum bearer token ('.self::TOKEN_NAME.'): '.$token);
        $this->command?->info('Sample attachment disk path: '.$attachment->path);
    }

    private function findOrCreateDemoUser(): User
    {
        return User::query()->updateOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Demo User',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );
    }

    private function findOrCreateProject(User $user): Project
    {
        return Project::query()->updateOrCreate(
            ['owner_id' => $user->id, 'name' => 'Demo Kanban Project'],
            [
                'description' => 'A pre-populated kanban project for the dev-manager demo.',
                'archived_at' => null,
            ],
        );
    }

    private function findOrCreateBoard(Project $project): Board
    {
        return Board::query()->updateOrCreate(
            ['project_id' => $project->id, 'name' => 'Demo Board'],
            [
                'position' => 'm',
                'archived_at' => null,
            ],
        );
    }

    /**
     * @return array<string, KanbanColumn>
     */
    private function findOrCreateColumns(Board $board): array
    {
        $names = ['Backlog', 'In Progress', 'Done'];
        $columns = [];

        foreach ($names as $index => $name) {
            // Position prefix grows by one 'a' per column so the seeded
            // ordering is stable: 'm' (Backlog), 'ma' (In Progress),
            // 'maa' (Done). The factory seed counter is irrelevant here.
            $columns[$name] = KanbanColumn::query()->updateOrCreate(
                ['board_id' => $board->id, 'name' => $name],
                [
                    'position' => 'm'.str_repeat('a', $index),
                    'archived_at' => null,
                ],
            );
        }

        return $columns;
    }

    /**
     * @param  array<string, KanbanColumn>  $columns
     * @return array<int, Card>
     */
    private function findOrCreateCards(array $columns): array
    {
        $cards = [];
        $titles = [
            'Backlog' => ['Set up project skeleton', 'Define acceptance criteria', 'Draft API contract'],
            'In Progress' => ['Implement kanban board UI'],
            'Done' => ['Initial commit'],
        ];

        foreach ($titles as $columnName => $columnTitles) {
            foreach ($columnTitles as $index => $title) {
                // Position: stable lex-rank prefix per card, scoped to the
                // column. Cards within a column get distinct positions so
                // order is observable on re-fetch.
                $cards[] = Card::query()->updateOrCreate(
                    ['column_id' => $columns[$columnName]->id, 'title' => $title],
                    [
                        'body' => "Auto-seeded card: {$title}.",
                        'position' => 'n'.str_repeat('a', $index + 1),
                        'archived_at' => null,
                    ],
                );
            }
        }

        return $cards;
    }

    /**
     * @param  array<int, Card>  $cards
     */
    private function findOrCreateComments(array $cards, User $user): void
    {
        // Two comments on the first card: one root, one same-author reply
        // (exercises thread-per-author semantics).
        $firstCard = $cards[0];

        $root = CardComment::query()->updateOrCreate(
            ['card_id' => $firstCard->id, 'author_id' => $user->id, 'parent_id' => null, 'body' => 'First root comment from demo user.'],
            [],
        );

        CardComment::query()->updateOrCreate(
            ['card_id' => $firstCard->id, 'author_id' => $user->id, 'parent_id' => $root->id, 'body' => 'Self-reply under my own root.'],
            [],
        );
    }

    private function findOrCreateAttachment(Card $card, User $user): CardAttachment
    {
        // Idempotency key: card + original_filename. The factory generates
        // a UUID-prefixed disk path, but we keep a stable on-disk filename
        // for the demo so the seed run is reproducible.
        $originalFilename = 'sample.png';

        $existing = CardAttachment::query()
            ->where('card_id', $card->id)
            ->where('original_filename', $originalFilename)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // Use the storage facade to write a real file under `local` so the
        // path is reproducible. `UploadedFile::fake()->image()` returns a
        // valid UploadedFile without touching disk, which we then push
        // through `Storage::disk('local')->putFileAs` — same code path as
        // the production controller.
        $uploaded = UploadedFile::fake()->image($originalFilename, 16, 16);
        $generatedName = Str::uuid()->toString().'.'.$uploaded->getClientOriginalExtension();
        $directory = "kanban/cards/{$card->id}";
        $storedPath = Storage::disk('local')->putFileAs($directory, $uploaded, $generatedName);

        if ($storedPath === false) {
            throw new \RuntimeException('DemoProjectSeeder failed to write attachment file.');
        }

        return DB::transaction(function () use ($card, $user, $uploaded, $originalFilename, $storedPath): CardAttachment {
            return CardAttachment::query()->create([
                'card_id' => $card->id,
                'uploader_id' => $user->id,
                'disk' => 'local',
                'path' => $storedPath,
                'original_filename' => $originalFilename,
                'mime' => $uploaded->getMimeType() ?? 'image/png',
                'size_bytes' => $uploaded->getSize() ?: 0,
            ]);
        });
    }

    private function findOrCreateToken(User $user): string
    {
        $existing = $user->tokens()->where('name', self::TOKEN_NAME)->first();

        if ($existing !== null) {
            // Sanctum tokens are stored hashed — we cannot recover the
            // plaintext. Delete + recreate on each seeder run so the
            // printed plaintext is always valid. The token count stays
            // at 1 (idempotent row count).
            $existing->delete();
        }

        return $user->createToken(self::TOKEN_NAME)->plainTextToken;
    }
}
