<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'description'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

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
        return $this->hasMany(Board::class);
    }
}
