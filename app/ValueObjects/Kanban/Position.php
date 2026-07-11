<?php

declare(strict_types=1);

namespace App\ValueObjects\Kanban;

use App\Exceptions\Kanban\PositionExhaustedException;
use InvalidArgumentException;

/**
 * Fractional-indexing value object using base-26 lowercase alphabetic
 * strings ('a'..'z'). Produces lexicographic keys for stable ordering
 * via `ORDER BY position ASC` against a varchar column.
 *
 * Locked public API (Batch 3 brief):
 *   - Position::between(?string $lower, ?string $upper): static
 *   - Position::after(string $previous): static
 *   - Position::start(): static
 *   - ->value(): string
 *
 * Hard cap: `MAX_LENGTH = 1024` UTF-8 bytes. The factory methods throw
 * `PositionExhaustedException` if no candidate fits within the cap.
 *
 * Algorithm (JIRA lexorank-style, simplified):
 *   1. Normalise nulls to ''.
 *   2. Walk lock-step until first divergence. The common prefix is kept.
 *   3. At divergence:
 *      - If `b[i] - a[i] >= 2`, midpoint a single char.
 *      - If adjacent (diff == 1), recurse on `s1[0..i+1] + between('', s2[i+1..])`.
 *   4. When one string is a prefix of the other:
 *      - If `s2` continues with char `c`: pick char strictly less than `c`.
 *      - If `s1` continues with char `c`: pick char strictly greater than `c`.
 *      - Boundary chars ('a' or 'z') trigger suffix recursion.
 *
 * Reference walkthroughs (Batch 3 brief):
 *   - between('a','c') -> 'b'        (single-char midpoint)
 *   - between('a','b') -> 'an'       (adjacent, recurse)
 *   - between('a','an') -> 'am'      (s2 is prefix-extension, pick one below 'n')
 *   - after('n') -> between('n','') -> 'u' (s1 continues with 'n'; pick upper-half)
 */
final class Position
{
    /**
     * Hard cap on the byte length of a position string.
     */
    public const int MAX_LENGTH = 1024;

    private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyz';

    private function __construct(
        private readonly string $value,
    ) {}

    public static function start(): static
    {
        return new self('n');
    }

    public static function between(?string $lower, ?string $upper): static
    {
        $candidate = self::computeMidpoint($lower ?? '', $upper ?? '');

        if (strlen($candidate) > self::MAX_LENGTH) {
            throw new PositionExhaustedException(sprintf(
                'Position string exceeds the %d-byte cap.',
                self::MAX_LENGTH,
            ));
        }

        return new self($candidate);
    }

    public static function after(string $previous): static
    {
        return self::between($previous, null);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Compute a midpoint between two non-null strings. An empty input on
     * either side means "open bound" — use the alphabet's start ('a') or
     * end ('z') as the effective boundary, respectively.
     */
    private static function computeMidpoint(string $a, string $b): string
    {
        // Edge: empty / empty -> alphabet midpoint.
        if ($a === '' && $b === '') {
            return 'n';
        }

        $lenA = strlen($a);
        $lenB = strlen($b);

        $prefix = '';
        $i = 0;

        while ($i < $lenA && $i < $lenB && $a[$i] === $b[$i]) {
            $prefix .= $a[$i];
            $i++;
        }

        $aDone = $i >= $lenA;
        $bDone = $i >= $lenB;

        // s1 is a prefix of s2: pick a char strictly less than s2[i].
        if ($aDone && ! $bDone) {
            $bChar = $b[$i];
            $idx = self::alphIndex($bChar);

            if ($idx === 0) {
                // Can't go below 'a'; recurse with s2's next char after we
                // emit s1 + s2[i] (which equals 'a', an effective prefix).
                $next = self::computeMidpoint('', substr($b, $i + 1));

                return $b[$i].$next;
            }

            $mid = intdiv($idx, 2);

            return $prefix.self::ALPHABET[$mid];
        }

        // s2 is a prefix of s1: pick a char strictly greater than s1[i].
        if ($bDone && ! $aDone) {
            $aChar = $a[$i];
            $idx = self::alphIndex($aChar);

            if ($idx === 25) {
                // Can't go above 'z'; we need to extend s1 by at least one
                // additional char and recurse. The risk is producing a
                // candidate equal to s1 (causing "after(s1)" loops to
                // s1 forever). Guard against the degenerate case: if s2 is
                // empty AND s1 ends with the alphabet max, the only way to
                // be strictly greater is to extend by *two* chars (we drop
                // into the divergent-suffix path which produces a value
                // strictly greater than the input). This is the documented
                // exhaustion guard: callers must catch
                // PositionExhaustedException and rebalance when this fires
                // (Batch 1.6 brief §6).
                throw new PositionExhaustedException(sprintf(
                    'Cannot append after %s — reached the alphabet boundary.',
                    $a,
                ));
            }

            // Strictly greater: between (idx, 25], midpoint = idx + ceil((25 - idx) / 2) + ... simpler.
            $mid = intdiv($idx + 25, 2) + 1;

            // Clamp to 25 (alpha-floor).
            if ($mid > 25) {
                $mid = 25;
            }

            return $prefix.self::ALPHABET[$mid];
        }

        // Both strings have a divergent char at i.
        $aChar = $a[$i];
        $bChar = $b[$i];
        $ai = self::alphIndex($aChar);
        $bi = self::alphIndex($bChar);

        if ($bi - $ai >= 2) {
            $mid = intdiv($ai + $bi, 2);

            return $prefix.self::ALPHABET[$mid];
        }

        // Adjacent: extend lower with one alphabet midpoint.
        $next = self::computeMidpoint('', substr($b, $i + 1));

        return $prefix.$aChar.$next;
    }

    private static function alphIndex(string $char): int
    {
        $idx = strpos(self::ALPHABET, $char);

        if ($idx === false) {
            throw new InvalidArgumentException(sprintf(
                'Position strings must use lowercase a..z; got "%s".',
                $char,
            ));
        }

        return $idx;
    }
}
