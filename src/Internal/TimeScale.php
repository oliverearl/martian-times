<?php

declare(strict_types=1);

namespace MartianTime\Internal;

use DateTimeImmutable;

final class TimeScale
{
    /**
     * Effective UTC dates and TAI minus UTC, in SI seconds.
     * Each entry applies from midnight at the start of its UTC date.
     * Verified against IERS Bulletin C 72 (2026-07-06).
     *
     * @see https://hpiers.obspm.fr/iers/bul/bulc/Leap_Second.dat
     * @see https://hpiers.obspm.fr/iers/bul/bulc/bulletinc.72
     *
     * @var array<string, int>
     */
    private const TAI_MINUS_UTC = [
        '1972-01-01' => 10,
        '1972-07-01' => 11,
        '1973-01-01' => 12,
        '1974-01-01' => 13,
        '1975-01-01' => 14,
        '1976-01-01' => 15,
        '1977-01-01' => 16,
        '1978-01-01' => 17,
        '1979-01-01' => 18,
        '1980-01-01' => 19,
        '1981-07-01' => 20,
        '1982-07-01' => 21,
        '1983-07-01' => 22,
        '1985-07-01' => 23,
        '1988-01-01' => 24,
        '1990-01-01' => 25,
        '1991-01-01' => 26,
        '1992-07-01' => 27,
        '1993-07-01' => 28,
        '1994-07-01' => 29,
        '1996-01-01' => 30,
        '1997-07-01' => 31,
        '1999-01-01' => 32,
        '2006-01-01' => 33,
        '2009-01-01' => 34,
        '2012-07-01' => 35,
        '2015-07-01' => 36,
        '2017-01-01' => 37,
    ];

    /**
     * Express an Earth instant as a Julian date: a continuous count of days,
     * with integer day boundaries at noon and fractions representing the time.
     *
     * The caller supplies a date normalized to UTC. Conversion uses PHP's
     * POSIX timestamp, which cannot represent an inserted leap-second instant.
     * Microseconds contribute to the calculation, but floating-point precision
     * limits how much detail survives in the large Julian-date value.
     *
     * @internal
     *
     * @see https://www.giss.nasa.gov/tools/mars24/help/algorithm.html Equation A-2.
     */
    public static function julianDateUtc(DateTimeImmutable $utc): float
    {
        // U is a string: this also avoids the 32-bit integer timestamp limit.
        // Add the positive fractional part separately for pre-1970 instants.
        $seconds = (float) $utc->format('U') + (int) $utc->format('u') / 1_000_000.0;

        return 2_440_587.5 + $seconds / 86_400.0;
    }

    /**
     * Find the SI seconds to add to UTC to obtain Terrestrial Time (TT).
     * The caller supplies a date normalized to UTC so table boundaries use
     * the correct calendar date.
     *
     * From January 1, 1972, the built-in leap-second table supplies TAI minus
     * UTC, to which 32.184 seconds is added. Earlier dates use NASA's polynomial
     * approximation. Dates beyond the table retain its last known offset;
     * this method does not predict future leap seconds or fetch new data.
     *
     * @internal
     *
     * @see https://www.giss.nasa.gov/tools/mars24/help/algorithm.html Equation A-4.
     */
    public static function ttMinusUtc(DateTimeImmutable $utc): float
    {
        if ((int) $utc->format('Y') < 1972) {
            $centuries = (self::julianDateUtc($utc) - 2_451_545.0) / 36_525.0;

            // NASA's historical approximation, rather than the modern table.
            return 64.184 + 59.0 * $centuries
                - 51.2 * $centuries ** 2
                - 67.1 * $centuries ** 3
                - 16.4 * $centuries ** 4;
        }

        // Numeric comparison also handles DateTime's extended years (> 9999).
        $date = (float) $utc->format('Ymd');
        $offset = 10;

        foreach (self::TAI_MINUS_UTC as $effectiveDate => $taiMinusUtc) {
            if ($date < (float) str_replace('-', '', $effectiveDate)) {
                break;
            }

            $offset = $taiMinusUtc;
        }

        return 32.184 + $offset;
    }
}
