<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the per-item result map for the bulk board endpoints
 * (`bulk-delete`, `bulk-rename`). The shape is:
 *
 *   {
 *     "data": {
 *       "results": [
 *         { "id": 1, "status": 204 },
 *         { "id": 2, "status": 409, "error": { "code": "board_has_contents" } },
 *         { "id": 9999, "status": 404, "error": { "code": "not_found" } }
 *       ],
 *       "summary": { "total": 3, "ok": 1, "failed": 2 }
 *     }
 *   }
 *
 * The controller builds the underlying array; this resource is responsible
 * for wrapping it under `data` (Laravel convention) AND for computing the
 * summary so callers don't have to.
 */
final class BulkOperationResultResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        /** @var array{results: list<array{id: int, status: int, error?: array<string, mixed>}>} $payload */
        $payload = $this->resource;

        $total = count($payload['results']);
        $ok = 0;
        $failed = 0;
        foreach ($payload['results'] as $entry) {
            if ($entry['status'] >= 200 && $entry['status'] < 300) {
                $ok++;
            } else {
                $failed++;
            }
        }

        return [
            'data' => [
                'results' => array_values($payload['results']),
                'summary' => [
                    'total' => $total,
                    'ok' => $ok,
                    'failed' => $failed,
                ],
            ],
        ];
    }
}
