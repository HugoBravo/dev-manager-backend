<?php

declare(strict_types=1);

use App\Models\Board;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;
use App\Policies\CommentPolicy;

/*
|--------------------------------------------------------------------------
| CommentPolicyTest — direct policy unit tests (Batch 5)
|--------------------------------------------------------------------------
|
| Exercises CommentPolicy methods directly so the documented 403 author-
| vs-author EXCEPTION is provable even in v1 where a single project has
| one owner (the HTTP layer returns 404 before reaching the policy for
| non-owners, but the policy rule still must be correct for a future
| membership feature).
|
*/

it('allows the comment author to update', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->forOwner($user)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();
    $comment = CardComment::factory()->forCard($card)->byAuthor($user)->create();

    expect((new CommentPolicy)->update($user, $comment))->toBeTrue();
});

it('forbids a different user from updating (403 EXCEPTION documented in design)', function (): void {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->forOwner($author)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();
    $comment = CardComment::factory()->forCard($card)->byAuthor($author)->create();

    // Direct policy invocation: this is the 403 EXCEPTION path. At the HTTP
    // layer in v1 a non-owner hits the 404 binding closure first because
    // there are no project memberships yet — but the policy is locked
    // correctly so a future `members` pivot does not require a policy change.
    expect((new CommentPolicy)->update($otherUser, $comment))->toBeFalse();
});

it('forbids a different user from deleting (403 EXCEPTION documented in design)', function (): void {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->forOwner($author)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();
    $comment = CardComment::factory()->forCard($card)->byAuthor($author)->create();

    expect((new CommentPolicy)->delete($otherUser, $comment))->toBeFalse();
});

it('allows the author to delete their own comment', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->forOwner($user)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();
    $comment = CardComment::factory()->forCard($card)->byAuthor($user)->create();

    expect((new CommentPolicy)->delete($user, $comment))->toBeTrue();
});

it('allows any authenticated user to create a comment (chokepoint upstream)', function (): void {
    expect((new CommentPolicy)->create(User::factory()->create()))->toBeTrue();
});

it('allows view for the project owner via the chain to ProjectPolicy', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();
    $comment = CardComment::factory()->forCard($card)->byAuthor($owner)->create();

    expect((new CommentPolicy)->view($owner, $comment))->toBeTrue();
});

it('forbids view for a non-owner via the chain to ProjectPolicy (binding should fire first at HTTP)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();
    $comment = CardComment::factory()->forCard($card)->byAuthor($owner)->create();

    expect((new CommentPolicy)->view($stranger, $comment))->toBeFalse();
});
