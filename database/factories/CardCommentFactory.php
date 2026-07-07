<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardComment>
 */
class CardCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'author_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->sentence(8),
        ];
    }

    /**
     * Indicate the comment belongs to a specific card.
     */
    public function forCard(Card $card): static
    {
        return $this->state(fn (): array => [
            'card_id' => $card->id,
        ]);
    }

    /**
     * Indicate the comment is authored by a specific user.
     */
    public function byAuthor(User $user): static
    {
        return $this->state(fn (): array => [
            'author_id' => $user->id,
        ]);
    }

    /**
     * Indicate the comment is a reply to a specific parent.
     */
    public function forParent(CardComment $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->id,
        ]);
    }
}
