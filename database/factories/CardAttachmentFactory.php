<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardAttachment>
 *
 * The factory does NOT generate real files on disk — the controller test
 * path uses `UploadedFile::fake()->image(...)` and `Storage::fake('local')`
 * to keep the production factory pure. Tests that need an `assertExists`
 * target call the controller's upload endpoint, not `create()` directly.
 */
class CardAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'uploader_id' => User::factory(),
            'disk' => 'local',
            'path' => 'kanban/cards/0/'.$this->faker->uuid().'.jpg',
            'original_filename' => $this->faker->word().'.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
        ];
    }

    /**
     * Indicate the attachment belongs to a specific card.
     */
    public function forCard(Card $card): static
    {
        return $this->state(fn (): array => [
            'card_id' => $card->id,
        ]);
    }

    /**
     * Indicate the attachment was uploaded by a specific user.
     */
    public function byUploader(User $user): static
    {
        return $this->state(fn (): array => [
            'uploader_id' => $user->id,
        ]);
    }
}
