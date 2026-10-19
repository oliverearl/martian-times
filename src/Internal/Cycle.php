<?php

declare(strict_types=1);

namespace MartianTime\Internal;

final class Cycle
{
    /**
     * Find the equivalent position in a repeating cycle, in [0, $period).
     * Fractional values are retained, and negative values wrap from the end
     * of the cycle: -1 with a period of 24 becomes 23.
     *
     * Callers must supply a finite value and a positive, finite period.
     * These preconditions are not checked by this internal helper.
     *
     * @internal
     */
    public static function wrap(float $value, float $period): float
    {
        $result = fmod($value, $period);

        if ($result < 0.0) {
            $result += $period;
        }

        // Addition can round a tiny negative remainder up to the period.
        return $result >= $period || $result == 0.0 ? 0.0 : $result;
    }
}
