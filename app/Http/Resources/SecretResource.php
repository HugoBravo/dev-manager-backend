<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Secret
 */
final class SecretResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'id' => $this->resource->id,
                'project_id' => $this->resource->project_id,
                'key' => $this->resource->key,
                'value' => $this->resource->value,
                'description' => $this->resource->description,
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
