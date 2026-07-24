<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\Tasks\TaskHasActiveBoardsException;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Fillable(['project_id', 'name', 'slug', 'description', 'status', 'priority', 'archived_at'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Generate a project-scoped slug when callers omit it or rename a task
     * without supplying a replacement slug.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            if (blank($task->slug) || ($task->isDirty('name') && ! $task->isDirty('slug'))) {
                $task->slug = self::generateUniqueSlug($task);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<KanbanBoard>
     */
    public function boards(): HasMany
    {
        return $this->hasMany(KanbanBoard::class, 'task_id');
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function hasActiveBoards(): bool
    {
        return Schema::hasColumn('kanban_boards', 'task_id')
            ? $this->boards()->whereNull('archived_at')->exists()
            : KanbanBoard::query()
                ->where('project_id', $this->project_id)
                ->whereNull('archived_at')
                ->exists();
    }

    public function archive(): void
    {
        if ($this->hasActiveBoards()) {
            throw new TaskHasActiveBoardsException($this);
        }

        $this->forceFill(['archived_at' => now()])->save();
    }

    public function restore(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }

    private static function generateUniqueSlug(self $task): string
    {
        $source = $task->isDirty('name') && ! $task->isDirty('slug')
            ? $task->name
            : ($task->slug ?: $task->name);
        $base = Str::slug((string) $source);
        $base = $base !== '' ? $base : 'task';
        $candidate = $base;
        $suffix = 2;

        while (self::query()
            ->where('project_id', $task->project_id)
            ->where('slug', $candidate)
            ->whereKeyNot($task->getKey())
            ->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
