<?php

declare(strict_types=1);

use App\Exceptions\Kanban\PositionExhaustedException;
use App\ValueObjects\Kanban\Position;

it('starts at the middle of the alphabet (n)', function (): void {
    $position = Position::start();

    expect($position->value())->toBe('n');
});

it('between two empty strings returns a sane 1-byte mid value', function (): void {
    $position = Position::between('', '');

    expect($position->value())->toBe('n')
        ->and(strlen($position->value()))->toBe(1);
});

it('between with no lower bound returns a value above the upper bound', function (): void {
    $position = Position::between(null, 'b');

    expect(strcmp($position->value(), 'b') < 0)->toBeTrue()
        ->and(strlen($position->value()))->toBeGreaterThanOrEqual(1);
});

it('between with no upper bound returns a value above the lower bound', function (): void {
    $position = Position::between('m', null);

    expect(strcmp($position->value(), 'm') > 0)->toBeTrue()
        ->and(strlen($position->value()))->toBeGreaterThanOrEqual(1);
});

it('between a and c returns b', function (): void {
    $position = Position::between('a', 'c');

    expect($position->value())->toBe('b');
});

it('between a and b returns an in-between 2-char value', function (): void {
    $position = Position::between('a', 'b');

    expect(strcmp($position->value(), 'a') > 0)->toBeTrue()
        ->and(strcmp($position->value(), 'b') < 0)->toBeTrue()
        ->and(strlen($position->value()))->toBeGreaterThanOrEqual(2);
});

it('between a and an returns an in-between value', function (): void {
    $position = Position::between('a', 'an');

    expect(strcmp($position->value(), 'a') > 0)->toBeTrue()
        ->and(strcmp($position->value(), 'an') < 0)->toBeTrue();
});

it('after n returns a value greater than n', function (): void {
    $position = Position::after('n');

    expect(strcmp($position->value(), 'n') > 0)->toBeTrue();
});

it('caps the produced value at 1024 bytes under repeated insert-extension in a growing gap', function (): void {
    // Adjacent first chars + suffix recursion is the only branch that
    // appends to the position string on each call. We force that branch
    // by feeding a sequence where lower is a prefix of upper, the upper
    // suffix is empty, and each call widens the gap. We assert that the
    // position string stays well within the cap regardless of pattern.
    $lower = 'a';
    $upper = 'b';

    $current = Position::between($lower, $upper)->value();
    expect(strlen($current))->toBeGreaterThanOrEqual(2);

    // 1000 iterations: assert no call exceeds the cap. The actual cap
    // may never be hit under this workload — the cap exists as a safety
    // valve for pathological inputs. The DB column is varchar(255);
    // the controller maps any > 1024 attempt to 422.
    for ($i = 0; $i < 1000; $i++) {
        $current = Position::between($lower, $current)->value();
        expect(strlen($current))->toBeLessThanOrEqual(Position::MAX_LENGTH);
    }
});

it('never produces a value longer than MAX_LENGTH even with adversarial cap-bounded input', function (): void {
    // The cap exists as a safety valve. The algorithm recurses on the
    // divergent suffix to keep the result compact; the cap guard fires
    // only on `after(z...z)` style exhaustion (Batch 1.6 brief §6).
    //
    // Contract:
    //   - Inputs that CAN produce a value return one <= MAX_LENGTH bytes.
    //   - Inputs at the alphabet boundary legitimately throw
    //     PositionExhaustedException — the controller catches this and
    //     triggers a rebalance.
    $cases = [
        'between(\'a\'×1024, \'b\'×1024)' => fn () => Position::between(str_repeat('a', Position::MAX_LENGTH), str_repeat('b', Position::MAX_LENGTH)),
        'between(\'\\0\', null)' => fn () => Position::between('', null),
        'start()' => fn () => Position::start(),
    ];

    foreach ($cases as $label => $factory) {
        $position = $factory();
        expect(strlen($position->value()))->toBeLessThanOrEqual(Position::MAX_LENGTH);
    }
});

it('exposes a public MAX_LENGTH constant of 1024', function (): void {
    expect(Position::MAX_LENGTH)->toBe(1024);
});

it('appends after the rightmost position repeatedly; exhausts after the alphabet boundary (Batch 1.6 rebalance contract)', function (): void {
    // The controller's append path calls `Position::after($rightmost)`
    // inside `lockForUpdate`. The contract is:
    //   - Each individual `->value()` stays <= MAX_LENGTH bytes while the
    //     algorithm has room.
    //   - When the input reaches the alphabet boundary ('z') the call
    //     legitimately throws PositionExhaustedException — the caller
    //     treats that as the rebalance trigger.
    //
    // We loop until the exception is raised (which IS the contract) and
    // then assert (a) the throw is the canonical exception, (b) each
    // intermediate value is strictly greater than the previous one.
    $current = 'n';
    $iterations = 0;

    try {
        while ($iterations < 30) {
            $next = Position::after($current)->value();
            expect(strlen($next))->toBeLessThanOrEqual(Position::MAX_LENGTH)
                ->and(strcmp($next, $current) > 0)->toBeTrue();
            $current = $next;
            $iterations++;
        }
        expect(true)->toBeFalse('append should have raised PositionExhaustedException before 30 iterations');
    } catch (PositionExhaustedException $e) {
        expect($iterations)->toBeGreaterThan(0);
    }
});
