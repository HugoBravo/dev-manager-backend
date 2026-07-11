<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ExampleDemoSeeder — seeds an end-to-end demo workspace (a few projects,
 * each with a couple of boards, the standard Kanban columns, and a handful
 * of cards) tied to the example user. It sits next to ExampleUserSeeder on
 * purpose: that seeder stays focused on giving you a login, while this one
 * gives you a workspace to click around in.
 *
 * Run with:
 *   php artisan db:seed --class=ExampleDemoSeeder
 *
 * Idempotent on every run: each row is keyed by a stable composite
 * (`owner_id` + `name` for Project, `project_id` + `name` for KanbanBoard,
 * `board_id` + `name` for KanbanColumn, `column_id` + `title` for
 * KanbanCard) so re-running the seeder refreshes existing rows in place
 * instead of duplicating them.
 *
 * Safe to run manually against any environment. It does not touch
 * DatabaseSeeder and only writes demo content; no destructive operations.
 */
final class ExampleDemoSeeder extends Seeder
{
    private const EXAMPLE_EMAIL = 'example@dev-manager.test';

    private const EXAMPLE_PASSWORD = 'password';

    private const EXAMPLE_NAME = 'Example User';

    /**
     * Demo projects seeded under the example user. Keyed by name (used as
     * the idempotency key) with the description as the value. Keep names
     * stable: changing them leaves the old project row in place.
     *
     * @var array<string, string>
     */
    private const DEMO_PROJECTS = [
        'Demo Workspace' => 'Workspace generado automáticamente por ExampleDemoSeeder para probar la app.',
        'API Platform' => 'Servicios backend, contratos HTTP y jobs asíncronos.',
        'Mobile App' => 'Cliente móvil: features, releases y bugs reportados desde QA.',
    ];

    /**
     * @var list<string>
     */
    private const BOARD_NAMES = [
        'Sprint actual',
        'Backlog general',
    ];

    /**
     * @var list<string>
     */
    private const COLUMN_NAMES = [
        'Backlog',
        'En curso',
        'En revisión',
        'Hecho',
    ];

    /**
     * Card content keyed by column name. Keep titles stable so the seeder
     * stays idempotent — changing a title here orphans the old row and
     * inserts a new one on the next run.
     *
     * @var array<string, list<array{title: string, body: ?string, due_in_days: ?int}>>
     */
    private const CARDS_BY_COLUMN = [
        'Backlog' => [
            ['title' => 'Configurar pipeline de CI', 'body' => 'Definir stages y secrets antes del primer merge.', 'due_in_days' => null],
            ['title' => 'Documentar endpoints públicos', 'body' => 'Generar referencia OpenAPI a partir de los Form Requests.', 'due_in_days' => 14],
        ],
        'En curso' => [
            ['title' => 'Implementar upload de archivos', 'body' => 'Subida con chunking + antivirus scan asíncrono.', 'due_in_days' => 3],
        ],
        'En revisión' => [
            ['title' => 'Refactor del módulo de auth', 'body' => 'Pendiente aprobación del security review.', 'due_in_days' => 1],
        ],
        'Hecho' => [
            ['title' => 'Configurar backups automatizados', 'body' => 'Cron diario + retención 30 días.', 'due_in_days' => null],
            ['title' => 'Migrar seeders al nuevo formato', 'body' => null, 'due_in_days' => null],
        ],
    ];

    public function run(): void
    {
        $user = $this->ensureExampleUser();

        $projectCount = 0;
        $boardCount = 0;
        $cardCount = 0;
        $projectSummaries = [];

        foreach (self::DEMO_PROJECTS as $projectName => $projectDescription) {
            $project = $this->ensureDemoProject($user, $projectName, $projectDescription);
            $projectSummaries[] = $project->name.' (slug: '.$project->slug.')';
            $projectCount++;

            foreach (self::BOARD_NAMES as $boardIndex => $boardName) {
                $board = $this->ensureBoard($project, $boardName, $boardIndex);
                $boardCount++;

                foreach (self::COLUMN_NAMES as $columnIndex => $columnName) {
                    $column = $this->ensureColumn($board, $columnName, $columnIndex);
                    $cardCount += $this->ensureCardsForColumn($column);
                }
            }
        }

        $this->command?->info('--- ExampleDemoSeeder complete ---');
        $this->command?->info('Example user id: '.$user->id);
        $this->command?->info('Projects seeded: '.$projectCount);
        foreach ($projectSummaries as $line) {
            $this->command?->info('  - '.$line);
        }
        $this->command?->info('Boards seeded: '.$boardCount);
        $this->command?->info('Cards seeded/updated: '.$cardCount);
    }

    private function ensureExampleUser(): User
    {
        return User::query()->updateOrCreate(
            ['email' => self::EXAMPLE_EMAIL],
            [
                'name' => self::EXAMPLE_NAME,
                'password' => Hash::make(self::EXAMPLE_PASSWORD),
                'email_verified_at' => now(),
            ],
        );
    }

    private function ensureDemoProject(User $user, string $name, string $description): Project
    {
        return Project::query()->updateOrCreate(
            ['owner_id' => $user->id, 'name' => $name],
            [
                'description' => $description,
            ],
        );
    }

    private function ensureBoard(Project $project, string $name, int $index): KanbanBoard
    {
        $board = KanbanBoard::query()
            ->where('project_id', $project->id)
            ->where('name', $name)
            ->first();

        if ($board === null) {
            $board = new KanbanBoard;
        }

        $board->project_id = $project->id;
        $board->name = $name;
        $board->position = $this->positionAt($index, count(self::BOARD_NAMES));
        $board->save();

        return $board;
    }

    private function ensureColumn(KanbanBoard $board, string $name, int $index): KanbanColumn
    {
        $column = KanbanColumn::query()
            ->where('board_id', $board->id)
            ->where('name', $name)
            ->first();

        if ($column === null) {
            $column = new KanbanColumn;
        }

        $column->board_id = $board->id;
        $column->name = $name;
        $column->position = $this->positionAt($index, count(self::COLUMN_NAMES));
        $column->save();

        return $column;
    }

    private function ensureCardsForColumn(KanbanColumn $column): int
    {
        $plan = self::CARDS_BY_COLUMN[$column->name] ?? [];
        $written = 0;

        foreach ($plan as $cardIndex => $card) {
            $existing = KanbanCard::query()
                ->where('column_id', $column->id)
                ->where('title', $card['title'])
                ->first();

            if ($existing === null) {
                $existing = new KanbanCard;
            }

            $existing->column_id = $column->id;
            $existing->title = $card['title'];
            $existing->body = $card['body'];
            $existing->position = $this->positionAt($cardIndex, max(1, count($plan)));
            $existing->due_date = $card['due_in_days'] === null
                ? null
                : now()->addDays($card['due_in_days'])->toDateString();
            $existing->save();

            $written++;
        }

        return $written;
    }

    /**
     * Generate a stable position string for demo data. The string lives in
     * the same a..z alphabet as {@see \App\ValueObjects\Kanban\Position},
     * which is the only vocabulary the rest of the app understands — using
     * any other character (e.g. base-36 '0'..'9') leaves invalid rows that
     * blow up `Position::after()` on the next move.
     *
     * Slots are spread evenly across the LEFT half of the alphabet
     * (a..m), leaving n..z free for the backend's `nextPositionFor*()`
     * append path. That way the first append after seeding lands in the
     * n..z range without colliding with seeded rows and never exhausts
     * the available space.
     */
    private function positionAt(int $index, int $slots): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';
        $leftHalfMax = 12; // 'a'..'m' inclusive — 13 chars; index 12 = 'm'.
        $step = intdiv($leftHalfMax, max(1, $slots));
        $offset = min($leftHalfMax, $step * $index);

        return $alphabet[$offset];
    }
}
