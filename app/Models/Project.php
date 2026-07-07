<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['owner_id', 'name', 'description', 'slug', 'archived_at'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'archived_at' => 'datetime',
    ];

    /**
     * Boot hooks. Auto-generates a unique URL-safe slug from `name` when the
     * model is created/renamed without an explicit slug — matching the Card
     * archive-filter UX from spec (#99) and giving the Angular client
     * predictable URLs regardless of who set the name.
     */
    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            if (blank($project->slug) || $project->isDirty('name')) {
                $project->slug = self::generateUniqueSlug($project);
            }
        });
    }

    /**
     * The user who owns this project (the chokepoint relation for ProjectPolicy).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Boards under this project. Forward-compatible with Batch 2 (boards
     * migration adds `project_id` FK cascadeOnDelete, see
     * 2026_07_07_010000_create_boards_table).
     */
    public function boards(): HasMany
    {
        return $this->hasMany(KanbanBoard::class);
    }

    /**
     * Compute a unique slug for this Project. Uses the current (possibly
     * dirty) `slug` if present, otherwise derives from `name`, then appends
     * `-2`, `-3`, ... on collision. Skips this row's own id when checking
     * uniqueness so re-saving the same record is a no-op.
     */
    private static function generateUniqueSlug(self $project): string
    {
        $base = $project->slug !== null && $project->slug !== ''
            ? Str::slug((string) $project->slug)
            : Str::slug((string) $project->name);

        if ($base === '') {
            $base = 'project';
        }

        $candidate = $base;
        $suffix = 2;

        while (self::query()
            ->where('slug', $candidate)
            ->whereKeyNot($project->getKey())
            ->exists()
        ) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
