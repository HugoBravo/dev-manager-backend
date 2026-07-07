<?php

declare(strict_types=1);

use App\Models\KanbanAttachment;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\KanbanComment;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoProjectSeeder;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| SeederTest (Batch 7 — DemoProjectSeeder shape contract)
|--------------------------------------------------------------------------
|
| Verifies the deterministic demo dataset exists after running
| `php artisan db:seed --class=DemoProjectSeeder`. The test asserts the
| expected row counts (1 user, 1 project, 1 board, 3 columns, 5 cards,
| >= 2 comments, >= 1 attachment) plus that the demo user has a Sanctum
| token named 'demo-token' so the Angular client (or curl) can hit the
| API immediately after seeding.
|
| Re-runs are idempotent — calling the seeder twice does not duplicate
| rows. This is enforced by `updateOrCreate`/factory state lookups in
| the seeder itself.
|
*/

it('seeds the demo user, project, board, columns, cards, comments and attachment', function (): void {
    Artisan::call('db:seed', ['--class' => DemoProjectSeeder::class, '--force' => true]);

    // User with verified email + known password.
    $demo = User::query()->where('email', 'demo@dev-manager.test')->first();
    expect($demo)->not->toBeNull()
        ->and($demo?->email_verified_at)->not->toBeNull();

    // Project name is unique-enough for the demo dataset.
    $project = Project::query()->where('name', 'Demo Kanban Project')->first();
    expect($project)->not->toBeNull();

    // 1 board, 3 columns, 5 cards in this dataset.
    expect(KanbanBoard::query()->where('project_id', $project?->id)->count())->toBe(1);
    expect(KanbanColumn::query()->whereIn(
        'board_id',
        KanbanBoard::query()->where('project_id', $project?->id)->pluck('id')->all()
    )->count())->toBe(3);

    $cardCount = KanbanCard::query()->whereIn(
        'column_id',
        KanbanColumn::query()->whereIn(
            'board_id',
            KanbanBoard::query()->where('project_id', $project?->id)->pluck('id')->all()
        )->pluck('id')->all()
    )->count();
    expect($cardCount)->toBe(5);

    // >= 2 comments + >= 1 attachment across the cards in this project.
    $commentCount = KanbanComment::query()->whereIn(
        'card_id',
        KanbanCard::query()->whereIn(
            'column_id',
            KanbanColumn::query()->whereIn(
                'board_id',
                KanbanBoard::query()->where('project_id', $project?->id)->pluck('id')->all()
            )->pluck('id')->all()
        )->pluck('id')->all()
    )->count();
    expect($commentCount)->toBeGreaterThanOrEqual(2);

    $attachmentCount = KanbanAttachment::query()->whereIn(
        'card_id',
        KanbanCard::query()->whereIn(
            'column_id',
            KanbanColumn::query()->whereIn(
                'board_id',
                KanbanBoard::query()->where('project_id', $project?->id)->pluck('id')->all()
            )->pluck('id')->all()
        )->pluck('id')->all()
    )->count();
    expect($attachmentCount)->toBeGreaterThanOrEqual(1);

    // Token named 'demo-token' exists for the demo user.
    $tokenCount = $demo?->tokens()->where('name', 'demo-token')->count() ?? 0;
    expect($tokenCount)->toBeGreaterThanOrEqual(1);
});

it('is idempotent — re-running the seeder does not duplicate rows', function (): void {
    Artisan::call('db:seed', ['--class' => DemoProjectSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => DemoProjectSeeder::class, '--force' => true]);

    $project = Project::query()->where('name', 'Demo Kanban Project')->first();
    expect($project)->not->toBeNull();

    // Counts are unchanged after a second pass.
    expect(KanbanBoard::query()->where('project_id', $project?->id)->count())->toBe(1);
    expect(KanbanColumn::query()->whereIn(
        'board_id',
        KanbanBoard::query()->where('project_id', $project?->id)->pluck('id')->all()
    )->count())->toBe(3);

    $cardCount = KanbanCard::query()->whereIn(
        'column_id',
        KanbanColumn::query()->whereIn(
            'board_id',
            KanbanBoard::query()->where('project_id', $project?->id)->pluck('id')->all()
        )->pluck('id')->all()
    )->count();
    expect($cardCount)->toBe(5);
});
