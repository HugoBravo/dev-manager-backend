<?php

declare(strict_types=1);

namespace App\Exceptions\Kanban;

use RuntimeException;

/**
 * Thrown when the `Position` value object cannot produce a string that
 * satisfies the 1024-byte `MAX_LENGTH` cap. Distinct from Laravel's HTTP
 * exception type so callers can branch on the domain failure without
 * touching Symfony / HTTP machinery.
 *
 * The controller layer maps this to HTTP 422 with a typed `code`
 * (`position_precision_exhausted`) per sdd/kanban/design §7. A rebalance
 * artisan command is OUT OF SCOPE for the kanban change — Batch 7 queue.
 */
final class PositionExhaustedException extends RuntimeException {}
