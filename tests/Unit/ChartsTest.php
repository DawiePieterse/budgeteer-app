<?php

use App\Support\Charts;

it('makes an axis of round steps that holds the largest value', function () {
    expect(Charts::axis(4_270_000))->toBe(['max' => 6_000_000, 'step' => 2_000_000, 'ticks' => [0, 2_000_000, 4_000_000, 6_000_000]])
        ->and(Charts::axis(900_000)['step'])->toBe(250_000) // R2.5k steps
        ->and(Charts::axis(6_100_000)['ticks'])->toBe([0, 2_000_000, 4_000_000, 6_000_000, 8_000_000])
        ->and(Charts::axis(4_000_000)['max'])->toBe(4_000_000) // exactly on a step
        ->and(Charts::axis(0)['ticks'])->toBe([0, 2500, 5000, 7500, 10000]) // nothing yet: R0 to R100
        ->and(Charts::axis(1)['max'])->toBe(10000);
});

it('writes axis amounts short', function () {
    expect(Charts::short(0))->toBe('R0')
        ->and(Charts::short(50000))->toBe('R500')
        ->and(Charts::short(250000))->toBe('R2.5k')
        ->and(Charts::short(4_000_000))->toBe('R40k')
        ->and(Charts::short(120_000_000))->toBe('R1.2m')
        ->and(Charts::short(-250000))->toBe('-R2.5k');
});

it('draws a bar rounded at the top and square at the foot', function () {
    expect(Charts::barPath(10, 20, 24, 100))->toBe('M10 120V24Q10 20 14 20H30Q34 20 34 24V120Z')
        ->and(Charts::barPath(10, 118, 24, 2))->toBe('M10 120V120Q10 118 12 118H32Q34 118 34 120V120Z') // a short bar: a smaller radius
        ->and(Charts::barPath(10, 120, 24, 0))->toBe('');
});

it('spreads a line\'s points evenly between its ends', function () {
    expect(Charts::points([0, 50, 100], 0, 200, 0, 100, 100))->toBe('0,100 100,50 200,0')
        ->and(Charts::points([-5], 0, 200, 0, 100, 100))->toBe('0,100'); // below the axis stays on it
});
