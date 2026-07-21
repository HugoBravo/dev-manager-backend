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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| SmokeTest (Batch 7 — end-to-end happy path)
|--------------------------------------------------------------------------
|
| Runs through the locked critical paths end-to-end using the
| DemoProjectSeeder's deterministic dataset, then exercises ONE happy
 * path per resource to prove the chain still holds:
 *
 *   Project → Board → Column → Card → Comment → Attachment → Cascade
 *
 * The test asserts each step returns the expected envelope shape AND
 * the cascade-delete behaviour (file removed from disk + row removed
 * from DB) for the card destroy path. It is intentionally a SINGLE
 * chained scenario so a regression anywhere in the chain surfaces as a
 * single failure with full context.
|
*/

beforeEach(function (): void {
    Storage::fake('local');
    // Seed the demo dataset. Use --force so the test runs regardless of
    // the environment's "production" guard inside DatabaseSeeder; we
    // call the seeder class directly.
    Artisan::call('db:seed', ['--class' => DemoProjectSeeder::class, '--force' => true]);
});

it('runs the full project → board → column → card → comment → attachment → cascade chain', function (): void {
    $demo = User::query()->where('email', 'demo@dev-manager.test')->firstOrFail();
    $project = Project::query()->where('name', 'Demo Kanban Project')->firstOrFail();
    $board = KanbanBoard::query()
        ->whereHas('task', fn ($q) => $q->where('project_id', $project->id))
        ->firstOrFail();
    $columns = KanbanColumn::query()->where('board_id', $board->id)->orderBy('position')->get();
    $cards = KanbanCard::query()->whereIn('column_id', $columns->pluck('id'))->orderBy('position')->get();

    // Sanity baseline — the seeded chain is intact.
    expect($columns)->toHaveCount(3)
        ->and($cards)->toHaveCount(5)
        ->and($cards->pluck('archived_at')->filter()->isEmpty())->toBeTrue();

    // ─── Project → 200 ────────────────────────────────────────────────
    $this->actingAs($demo, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id);

    // ─── Board → 200 ──────────────────────────────────────────────────
    $this->actingAs($demo, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $board->id);

    // ─── Column → 200 ─────────────────────────────────────────────────
    $firstColumn = $columns->first();
    $this->actingAs($demo, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $firstColumn->id);

    // ─── Card → 200 ───────────────────────────────────────────────────
    $firstCard = $cards->first();
    $cardShowResponse = $this->actingAs($demo, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}/cards/{$firstCard->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $firstCard->id);

    // ─── Comment → 201 (post) + 200 (index) ───────────────────────────
    $commentResponse = $this->actingAs($demo, 'sanctum')
        ->postJson(
            kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}/cards/{$firstCard->id}/comments",
            ['body' => 'Smoke test comment'],
        )
        ->assertCreated()
        ->assertJsonPath('data.body', 'Smoke test comment');
    $newCommentId = $commentResponse->json('data.id');
    expect($newCommentId)->toBeInt();

    $this->actingAs($demo, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}/cards/{$firstCard->id}/comments")
        ->assertOk()
        ->assertJsonFragment(['id' => $newCommentId]);

    // ─── Attachment → 201 (post) + 200 (index) ────────────────────────
    $uploadResponse = $this->actingAs($demo, 'sanctum')
        ->postJson(
            kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}/cards/{$firstCard->id}/attachments",
            ['file' => UploadedFile::fake()->image('smoke.png', 8, 8)],
        )
        ->assertCreated();
    $newAttachmentId = $uploadResponse->json('data.id');
    $newAttachmentUrl = $uploadResponse->json('data.url');

    expect($newAttachmentId)->toBeInt()
        ->and($newAttachmentUrl)->toBeString();

    // The on-disk path is whatever Storage::disk('local')->putFileAs returned.
    // Look it up via the row (id is unique).
    $newAttachmentPath = KanbanAttachment::query()->whereKey($newAttachmentId)->value('path');
    expect($newAttachmentPath)->toBeString();

    // File is on disk.
    Storage::disk('local')->assertExists($newAttachmentPath);

    // Index includes the new attachment.
    $this->actingAs($demo, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}/cards/{$firstCard->id}/attachments")
        ->assertOk()
        ->assertJsonFragment(['id' => $newAttachmentId]);

    // ─── Cascade ──────────────────────────────────────────────────────
    // Hard-delete the card; FK CASCADE removes comments + attachments
    // rows, the CascadesKanbanCardFiles trait removes the attachment file.
    $this->actingAs($demo, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}/cards/{$firstCard->id}")
        ->assertNoContent();

    // Card row gone.
    expect(KanbanCard::query()->whereKey($firstCard->id)->exists())->toBeFalse();

    // Newly-created comment row gone (FK cascade).
    expect(KanbanComment::query()->whereKey($newCommentId)->exists())->toBeFalse();

    // Newly-created attachment row gone.
    expect(KanbanAttachment::query()->whereKey($newAttachmentId)->exists())->toBeFalse();

    // Attachment file removed from disk.
    Storage::disk('local')->assertMissing($newAttachmentPath);

    // ─── 404 on re-fetch ──────────────────────────────────────────────
    $this->actingAs($demo, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$firstColumn->id}/cards/{$firstCard->id}")
        ->assertNotFound();
});
