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

#[Fillable(['project_id', 'name', 'slug', 'description', 'status', 'archived_at'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

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

    public function archive(): void
    {
        $hasActiveBoards = Schema::hasColumn('kanban_boards', 'task_id')
            ? $this->boards()->whereNull('archived_at')->exists()
            : KanbanBoard::query()
                ->where('project_id', $this->project_id)
                ->whereNull('archived_at')
                ->exists();

        if ($hasActiveBoards) {
            throw new TaskHasActiveBoardsException($this);
        }

        $this->forceFill(['archived_at' => now()])->save();
    }

    public function restore(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }
}
