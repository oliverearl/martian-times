<?php

declare(strict_types=1);

namespace MartianTime\Tests\Unit;

use DateTimeImmutable;
use MartianTime\MarsTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeScaleTest extends TestCase
{
    #[DataProvider('leapSecondBoundaries')]
    public function testLeapOffsetStartsAtUtcMidnight(string $date, int $taiMinusUtc, ?int $previousTaiMinusUtc): void
    {
        $midnight = new DateTimeImmutable($date . 'T00:00:00Z');
        $at = MarsTime::fromDateTime($midnight);

        self::assertEqualsWithDelta($taiMinusUtc + 32.184, $at->terrestrialTimeOffset(), 1.0e-12);

        if ($previousTaiMinusUtc !== null) {
            $justBefore = MarsTime::fromDateTime($midnight->modify('-1 microsecond'));
            self::assertEqualsWithDelta($previousTaiMinusUtc + 32.184, $justBefore->terrestrialTimeOffset(), 1.0e-12);
        }
    }

    /** @return iterable<string, array{string, int, ?int}> */
    public static function leapSecondBoundaries(): iterable
    {
        $boundaries = [
            '1972-01-01' => 10, '1972-07-01' => 11, '1973-01-01' => 12,
            '1974-01-01' => 13, '1975-01-01' => 14, '1976-01-01' => 15,
            '1977-01-01' => 16, '1978-01-01' => 17, '1979-01-01' => 18,
            '1980-01-01' => 19, '1981-07-01' => 20, '1982-07-01' => 21,
            '1983-07-01' => 22, '1985-07-01' => 23, '1988-01-01' => 24,
            '1990-01-01' => 25, '1991-01-01' => 26, '1992-07-01' => 27,
            '1993-07-01' => 28, '1994-07-01' => 29, '1996-01-01' => 30,
            '1997-07-01' => 31, '1999-01-01' => 32, '2006-01-01' => 33,
            '2009-01-01' => 34, '2012-07-01' => 35, '2015-07-01' => 36,
            '2017-01-01' => 37,
        ];
        $previousTaiMinusUtc = null;

        foreach ($boundaries as $date => $taiMinusUtc) {
            yield $date => [$date, $taiMinusUtc, $previousTaiMinusUtc];
            $previousTaiMinusUtc = $taiMinusUtc;
        }
    }

    public function testCurrentIersOffset(): void
    {
        $mars = MarsTime::fromDateTime(new DateTimeImmutable('2026-12-31T23:59:59Z'));

        self::assertEqualsWithDelta(69.184, $mars->terrestrialTimeOffset(), 1.0e-12);
    }

    public function testPre1972DatesUseTheNasaHistoricalPolynomial(): void
    {
        $mars = MarsTime::fromDateTime(new DateTimeImmutable('1970-01-01T00:00:00Z'));

        self::assertSame(2440587.5, $mars->julianDateUtc());
        self::assertEqualsWithDelta(43.55486, $mars->terrestrialTimeOffset(), 1.0e-10);
    }
}
