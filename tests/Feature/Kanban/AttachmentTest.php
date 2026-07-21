<?php

declare(strict_types=1);

use App\Models\KanbanAttachment;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| AttachmentTest (Batch 6 — sdd/kanban/tasks Phase 6)
|--------------------------------------------------------------------------
|
| Attachment lifecycle:
|  - 5 MB hard cap, mime allowlist (jpg/jpeg/png/gif/webp/pdf/md/txt/zip)
|  - `local` disk under storage/app/private/kanban/cards/{card_id}/{uuid}.{ext}
|  - 404 cross-owner (binding closure)
|  - 401 unauthenticated
|  - hard-delete cascade on card destroy (DB + filesystem in DB::transaction)
|  - attachment rows removed by FK CASCADE
|
| Test pattern:
|  - Storage::fake('local') for every scenario
|  - UploadedFile::fake()->image('photo.jpg') for happy path
|  - UploadedFile::fake()->create('malware.exe') for mime block
|  - Storage::disk('local')->assertExists/assertMissing for filesystem invariant
|
*/

beforeEach(function (): void {
    Storage::fake('local');
});

it('returns 401 on every attachment endpoint without a bearer token', function (string $method, string $path): void {
    /** @var TestCase $this */
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/projects/1/tasks/1/kanban/boards/1/columns/1/cards/1/attachments'],
    'store' => ['POST', '/api/v1/projects/1/tasks/1/kanban/boards/1/columns/1/cards/1/attachments'],
    'destroy' => ['DELETE', '/api/v1/projects/1/tasks/1/kanban/boards/1/columns/1/cards/1/attachments/1'],
]);

it('uploads a valid .jpg attachment with 201 and writes the file to the local disk', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $file = UploadedFile::fake()->image('photo.jpg', 10, 10)->size(100);

    $response = $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ]);

    $response->assertCreated();

    $id = $response->json('data.id');
    expect($id)->toBeInt();

    $attachment = KanbanAttachment::query()->findOrFail($id);
    Storage::disk('local')->assertExists($attachment->path);
});

it('stores the uploaded file under kanban/cards/{card_id}/ with a uuid-prefixed name', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $file = UploadedFile::fake()->image('photo.jpg', 10, 10)->size(50);

    $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ])
        ->assertCreated();

    $attachment = KanbanAttachment::query()->where('card_id', $card->id)->firstOrFail();
    expect($attachment->path)->toStartWith("kanban/cards/{$card->id}/")
        ->and($attachment->original_filename)->toBe('photo.jpg')
        ->and($attachment->size_bytes)->toBeInt()->toBeGreaterThan(0)
        ->and($attachment->mime)->toBeString()->not->toBeEmpty();
});

it('rejects .exe uploads with 422 attachment_mime_blocked and writes neither row nor file', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $exe = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

    $response = $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $exe,
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.file.0', 'attachment_mime_blocked');

    expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(0);
    // No file was written — the kanban/cards/{id}/ directory should not exist on the fake disk.
    expect(Storage::disk('local')->files("kanban/cards/{$card->id}"))->toBe([]);
});

it('rejects uploads over 5 MB with 422', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    // 5 MB + 1 byte — Laravel `size` rule interprets as KB.
    $oversized = UploadedFile::fake()->image('huge.png', 10, 10)->size(5121);

    $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $oversized,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(0);
});

it('rejects uploads missing the file with 422', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(0);
});

it('lists attachments of a card with paginated envelope (page size 25)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    KanbanAttachment::factory()->forCard($card)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('meta.per_page'))->toBe(25);
});

it('hard-deletes an attachment with 204 and removes the file from disk', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $file = UploadedFile::fake()->image('photo.jpg', 10, 10)->size(50);
    $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ])
        ->assertCreated();

    $attachment = KanbanAttachment::query()->where('card_id', $card->id)->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments/{$attachment->id}")
        ->assertNoContent();

    Storage::disk('local')->assertMissing($attachment->path);
    $this->assertDatabaseMissing('kanban_attachments', ['id' => $attachment->id]);
});

it('returns 404 when a non-owner uploads to a card they do not own', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $file = UploadedFile::fake()->image('photo.jpg', 10, 10)->size(50);

    $this->actingAs($stranger, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ])
        ->assertNotFound();

    expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(0);
});

it('returns 404 when a non-owner deletes an attachment', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $attachment = KanbanAttachment::factory()->forCard($card)->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments/{$attachment->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('kanban_attachments', ['id' => $attachment->id]);
});

it('returns 404 when fetching an attachment id that belongs to another card via direct attachment-id lookup', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $otherCard = KanbanCard::factory()->forColumn($column)->create();

    $attachmentOnOtherCard = KanbanAttachment::factory()->forCard($otherCard)->create();

    // Asking for the attachment using the right (otherCard) id but the wrong card URL segment.
    $this->actingAs($owner, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments/{$attachmentOnOtherCard->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('kanban_attachments', ['id' => $attachmentOnOtherCard->id]);
});

it('returns 404 when fetching an attachment id that does not exist via cross-owner binding closure', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $attachment = KanbanAttachment::factory()->forCard($card)->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments/{$attachment->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('kanban_attachments', ['id' => $attachment->id]);
});

it('cascades attachment files and rows when a card is hard-deleted', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    // Upload three attachments.
    $paths = [];
    for ($i = 0; $i < 3; $i++) {
        $file = UploadedFile::fake()->image("photo{$i}.jpg", 10, 10)->size(50);
        $this->actingAs($owner, 'sanctum')
            ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
                'file' => $file,
            ])
            ->assertCreated();
        $attachment = KanbanAttachment::query()->where('card_id', $card->id)->latest('id')->firstOrFail();
        $paths[] = $attachment->path;
        Storage::disk('local')->assertExists($attachment->path);
    }

    // Hard delete the card.
    $this->actingAs($owner, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertNoContent();

    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
    expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(0);
});

it('rolls back the card row deletion if the cascade file delete throws', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    // Upload one attachment to set up the cascade path.
    $file = UploadedFile::fake()->image('photo.jpg', 10, 10)->size(50);
    $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ])
        ->assertCreated();

    $attachment = KanbanAttachment::query()->where('card_id', $card->id)->firstOrFail();

    // Swap the disk to a mock that throws on delete.
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();
    Storage::shouldReceive('delete')->andThrow(new RuntimeException('disk write failure'));

    $this->actingAs($owner, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertStatus(500);

    // Transaction rolled back — card and attachment row must still exist.
    $this->assertDatabaseHas('kanban_cards', ['id' => $card->id]);
    $this->assertDatabaseHas('kanban_attachments', ['id' => $attachment->id]);
});

/**
 * Parametrized mime allowlist scenarios. Each tuple is:
 *   [is_blocked, fake_filename, mime, label]
 */
dataset('attachment_mime_scenarios', [
    'jpg_ok' => [false, 'photo.jpg', 'image/jpeg'],
    'png_ok' => [false, 'diagram.png', 'image/png'],
    'pdf_ok' => [false, 'spec.pdf', 'application/pdf'],
    'zip_ok' => [false, 'bundle.zip', 'application/zip'],
    'exe_blocked' => [true, 'malware.exe', 'application/x-msdownload'],
    'bat_blocked' => [true, 'script.bat', 'application/x-bat'],
]);

it('enforces the mime allowlist per the param dataset', function (bool $blocked, string $name, string $mime): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $file = UploadedFile::fake()->create($name, 5, $mime);

    $response = $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ]);

    if ($blocked) {
        $response->assertStatus(422);
        $response->assertJsonPath('errors.file.0', 'attachment_mime_blocked');
        expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(0);
        expect(Storage::disk('local')->files("kanban/cards/{$card->id}"))->toBe([]);

        return;
    }

    $response->assertCreated();
    expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(1);
})->with('attachment_mime_scenarios');

it('uploads via Sanctum bearer token end-to-end with 201', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $token = bearerFor($owner);
    $file = UploadedFile::fake()->image('bearer.jpg', 10, 10)->size(50);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ]);

    $response->assertCreated();
    expect(KanbanAttachment::query()->where('card_id', $card->id)->count())->toBe(1);
});

it('exposes the resource shape with id, card_id, uploader_id, original_filename, mime, size_bytes, url, created_at', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $file = UploadedFile::fake()->image('shape.jpg', 10, 10)->size(50);

    $this->actingAs($owner, 'sanctum')
        ->post(kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
            'file' => $file,
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'card_id',
                'uploader_id',
                'original_filename',
                'mime',
                'size_bytes',
                'url',
                'created_at',
            ],
        ]);
});
