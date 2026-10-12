<?php

namespace App\Support;

/**
 * Geometry for the Overview's charts, drawn as SVG on the server (the security policy allows no chart
 * library or inline styles): round axis steps, short amount labels, and bars rounded only at the top.
 */
final class Charts
{
    /**
     * An axis from 0 that holds $max in a few round steps (1, 2, 2.5 or 5 times a power of ten).
     *
     * @return array{max: int, step: int, ticks: list<int>}
     */
    public static function axis(int $max, int $steps = 4): array
    {
        // An axis reaches at least R100, so a month with next to nothing still gets whole-rand steps.
        $raw = max($max, 10000) / $steps;
        $magnitude = 10 ** floor(log10($raw));
        $step = (int) $magnitude;
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $magnitude >= $raw) {
                $step = (int) round($m * $magnitude);
                break;
            }
        }
        $count = (int) ceil(max($max, 10000) / $step);

        return ['max' => $step * $count, 'step' => $step, 'ticks' => array_map(fn ($i) => $i * $step, range(0, $count))];
    }

    /** An amount for an axis, short: R0, R500, R2.5k, R40k, R1.2m. */
    public static function short(int $cents): string
    {
        $rand = abs($cents) / 100;
        $text = match (true) {
            $rand >= 1_000_000 => self::trim($rand / 1_000_000).'m',
            $rand >= 1_000 => self::trim($rand / 1_000).'k',
            default => (string) round($rand),
        };

        return ($cents < 0 ? '-' : '').'R'.$text;
    }

    /** A bar from the baseline up, with 4px rounded top corners and a square foot. */
    public static function barPath(float $x, float $y, float $width, float $height, float $radius = 4): string
    {
        if ($height <= 0) {
            return '';
        }
        $r = min($radius, $height, $width / 2);
        $f = fn (float $n) => round($n, 2);
        $bottom = $y + $height;

        return 'M'.$f($x).' '.$f($bottom).'V'.$f($y + $r).'Q'.$f($x).' '.$f($y).' '.$f($x + $r).' '.$f($y)
            .'H'.$f($x + $width - $r).'Q'.$f($x + $width).' '.$f($y).' '.$f($x + $width).' '.$f($y + $r).'V'.$f($bottom).'Z';
    }

    /**
     * SVG points for a line through values, spread evenly from $left to $right, with $top for the axis's
     * maximum and $bottom for 0.
     *
     * @param  list<int>  $values
     */
    public static function points(array $values, float $left, float $right, float $top, float $bottom, int $max, ?int $slots = null): string
    {
        $slots ??= count($values);
        $step = $slots > 1 ? ($right - $left) / ($slots - 1) : 0;

        return implode(' ', array_map(
            fn (int $v, int $i) => round($left + $i * $step, 2).','.round($bottom - ($bottom - $top) * max(0, $v) / max(1, $max), 2),
            $values, array_keys($values)));
    }

    private static function trim(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
