<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'key', 'value', 'description'])]
class Secret extends Model
{
    /** @use HasFactory<SecretFactory> */
    use HasFactory;

    /**
     * Cast the secret value to encrypted at rest via Laravel's
     * `encrypted` cast (uses the app key). Reading returns the
     * decrypted plaintext string.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    /**
     * The project this secret belongs to — the authorization chokepoint
     * for SecretPolicy (delegates to ProjectPolicy::view).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
