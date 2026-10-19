<?php

declare(strict_types=1);

namespace MartianTime\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use MartianTime\MarsTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarsTimeTest extends TestCase
{
    public function testNasaJanuary2000WorkedExample(): void
    {
        $mars = MarsTime::fromDateTime(new DateTimeImmutable('2000-01-06T00:00:00Z'));

        self::assertEqualsWithDelta(2451549.5, $mars->julianDateUtc(), 1.0e-9);
        self::assertEqualsWithDelta(64.184, $mars->terrestrialTimeOffset(), 1.0e-12);
        self::assertEqualsWithDelta(2451549.5007428704, $mars->julianDateTerrestrial(), 1.0e-9);
        self::assertEqualsWithDelta(44795.99976039436, $mars->marsSolDate(), 1.0e-8);

        // NASA's intermediate values are rounded, not exact reference values.
        self::assertEqualsWithDelta(277.18758, $mars->solarLongitudeDegrees(), 2.0e-5);
        self::assertEqualsWithDelta(-5.18774, $mars->equationOfTimeDegrees(), 2.0e-5);
        self::assertEqualsWithDelta(-0.34585, $mars->equationOfTimeHours(), 1.0e-5);
        self::assertClockEqualsWithDelta(23.99425, $mars->meanSolarTime()->hours(), 1.0 / 3600.0);
        self::assertClockEqualsWithDelta(23.64840, $mars->localTrueSolarTime()->hours(), 1.0 / 3600.0);
        self::assertSame('23:59:39', $mars->meanSolarTime()->format());
        self::assertSame('23:38:54', $mars->localTrueSolarTime()->format());
        self::assertSame($mars->meanSolarTime()->hours(), $mars->coordinatedMarsTime()->hours());
    }

    public function testNasaSpiritWorkedExampleUsesWestPositiveLongitude(): void
    {
        $mars = MarsTime::fromDateTime(new DateTimeImmutable('2004-01-03T13:46:31Z'), 184.702);

        self::assertEqualsWithDelta(2453008.0739699076, $mars->julianDateUtc(), 1.0e-9);
        self::assertEqualsWithDelta(2453008.074712778, $mars->julianDateTerrestrial(), 1.0e-9);
        self::assertEqualsWithDelta(327.32416, $mars->solarLongitudeDegrees(), 2.0e-5);
        self::assertEqualsWithDelta(-12.77553, $mars->equationOfTimeDegrees(), 2.0e-5);
        self::assertClockEqualsWithDelta(13.16537, $mars->meanSolarTime()->hours(), 1.0 / 3600.0);
        self::assertClockEqualsWithDelta(0.85190, $mars->localMeanSolarTime()->hours(), 1.0 / 3600.0);
        self::assertClockEqualsWithDelta(0.00025, $mars->localTrueSolarTime()->hours(), 1.0 / 3600.0);
        self::assertSame('00:51:06', $mars->localMeanSolarTime()->format());
        self::assertSame('00:00:00', $mars->localTrueSolarTime()->format());
    }

    public function testUtcConversionPreservesEquivalentInstantsAndMicroseconds(): void
    {
        $utc = MarsTime::fromDateTime(new DateTimeImmutable('2004-01-03T13:46:31.123456Z'));
        $newYork = MarsTime::fromDateTime(new DateTimeImmutable('2004-01-03 08:46:31.123456 America/New_York'));

        self::assertSame('2004-01-03 13:46:31.123456 UTC', $utc->earthTime()->format('Y-m-d H:i:s.u e'));
        self::assertSame($utc->julianDateUtc(), $newYork->julianDateUtc());
        self::assertSame($utc->marsSolDate(), $newYork->marsSolDate());
    }

    public function testMutableEarthInputIsNeitherChangedNorRetained(): void
    {
        $earth = new DateTime('2024-05-10T11:12:13.456789+02:00');
        $original = $earth->format('Y-m-d H:i:s.u P');
        $mars = MarsTime::fromDateTime($earth);

        self::assertSame($original, $earth->format('Y-m-d H:i:s.u P'));
        self::assertInstanceOf(DateTimeImmutable::class, $mars->earthTime());
        $earth->modify('+1 day')->setTimezone(new DateTimeZone('Pacific/Auckland'));
        self::assertSame('2024-05-10 09:12:13.456789 UTC', $mars->earthTime()->format('Y-m-d H:i:s.u e'));
    }

    public function testNegativeUnixTimestampsPreservePositiveMicrosecondFractions(): void
    {
        $halfSecondBeforeEpoch = MarsTime::fromDateTime(new DateTimeImmutable('1969-12-31T23:59:59.500000Z'));
        $oneAndHalfSecondsBeforeEpoch = MarsTime::fromDateTime(new DateTimeImmutable('1969-12-31T23:59:58.500000Z'));

        self::assertEqualsWithDelta(2440587.5 - 0.5 / 86400.0, $halfSecondBeforeEpoch->julianDateUtc(), 1.0e-9);
        self::assertEqualsWithDelta(2440587.5 - 1.5 / 86400.0, $oneAndHalfSecondsBeforeEpoch->julianDateUtc(), 1.0e-9);
        self::assertEqualsWithDelta(
            1.0,
            ($halfSecondBeforeEpoch->julianDateUtc() - $oneAndHalfSecondsBeforeEpoch->julianDateUtc()) * 86400.0,
            5.0e-5,
        );
    }

    public function testWestPositiveLongitudeOffsetsTheClock(): void
    {
        $earth = new DateTimeImmutable('2004-01-03T13:46:31Z');
        $prime = MarsTime::fromDateTime($earth);
        $west = MarsTime::fromDateTime($earth, 15.0);
        $east = MarsTime::fromDateTime($earth, -15.0);

        self::assertEqualsWithDelta($prime->meanSolarTime()->hours() - 1.0, $west->localMeanSolarTime()->hours(), 1.0e-10);
        self::assertEqualsWithDelta($prime->meanSolarTime()->hours() + 1.0, $east->localMeanSolarTime()->hours(), 1.0e-10);
        self::assertSame(345.0, $east->longitudeWest());
        self::assertSame(0.0, MarsTime::fromDateTime($earth, 360.0)->longitudeWest());
    }

    #[DataProvider('equivalentLongitudes')]
    public function testEquivalentLongitudesAreNormalized(float $longitude): void
    {
        $mars = MarsTime::fromDateTime(new DateTimeImmutable('2004-01-03T13:46:31Z'), $longitude);

        self::assertEqualsWithDelta(184.702, $mars->longitudeWest(), 1.0e-10);
        self::assertClockEqualsWithDelta(0.8519021282625694, $mars->localMeanSolarTime()->hours(), 1.0e-8);
    }

    /** @return iterable<string, array{float}> */
    public static function equivalentLongitudes(): iterable
    {
        yield 'west' => [184.702];
        yield 'additional full turn' => [544.702];
        yield 'negative east equivalent' => [-175.298];
    }

    public function testLocalClocksWrapAcrossMidnight(): void
    {
        $earth = new DateTimeImmutable('2004-01-03T13:46:31Z');
        $west = MarsTime::fromDateTime($earth, 210.0);
        $east = MarsTime::fromDateTime($earth, -180.0);
        $beforeTrueMidnight = MarsTime::fromDateTime($earth, 184.717);

        self::assertClockEqualsWithDelta(23.165368794929236, $west->localMeanSolarTime()->hours(), 1.0e-8);
        self::assertClockEqualsWithDelta(1.165368794929236, $east->localMeanSolarTime()->hours(), 1.0e-8);
        self::assertGreaterThanOrEqual(23.0, $beforeTrueMidnight->localTrueSolarTime()->hours());

        foreach ([$west, $east, $beforeTrueMidnight] as $mars) {
            foreach ([$mars->meanSolarTime(), $mars->localMeanSolarTime(), $mars->localTrueSolarTime()] as $clock) {
                self::assertGreaterThanOrEqual(0.0, $clock->hours());
                self::assertLessThan(24.0, $clock->hours());
            }
        }
    }

    public function testAnExplicitTtOffsetChangesTheComputedInstant(): void
    {
        $earth = new DateTimeImmutable('2000-01-06T00:00:00Z');
        $automatic = MarsTime::fromDateTime($earth);
        $override = MarsTime::fromDateTime($earth, 0.0, 100.0);
        $differenceSeconds = 100.0 - 64.184;

        self::assertSame(100.0, $override->terrestrialTimeOffset());
        self::assertSame($automatic->julianDateUtc(), $override->julianDateUtc());
        self::assertEqualsWithDelta(
            $differenceSeconds / 86400.0,
            $override->julianDateTerrestrial() - $automatic->julianDateTerrestrial(),
            1.0e-9,
        );
        self::assertClockEqualsWithDelta(
            $automatic->meanSolarTime()->hours() + $differenceSeconds / (3600.0 * 1.0274912517) - 24.0,
            $override->meanSolarTime()->hours(),
            5.0e-8,
        );
        self::assertSame(0.0, MarsTime::fromDateTime($earth, 0.0, 0.0)->terrestrialTimeOffset());
    }

    #[DataProvider('nonFiniteValues')]
    public function testNonFiniteLongitudesAreRejected(float $longitude): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarsTime::fromDateTime(new DateTimeImmutable('2000-01-06T00:00:00Z'), $longitude);
    }

    #[DataProvider('nonFiniteValues')]
    public function testNonFiniteTtOffsetsAreRejected(float $offset): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarsTime::fromDateTime(new DateTimeImmutable('2000-01-06T00:00:00Z'), 0.0, $offset);
    }

    /** @return iterable<string, array{float}> */
    public static function nonFiniteValues(): iterable
    {
        yield 'positive infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'not a number' => [NAN];
    }

    public function testNowRecordsACurrentUtcInstantAndAcceptsLocationAndOffset(): void
    {
        $before = microtime(true);
        $mars = MarsTime::now(45.0, 99.0);
        $after = microtime(true);
        $captured = (float) $mars->earthTime()->format('U') + (int) $mars->earthTime()->format('u') / 1_000_000.0;

        self::assertGreaterThanOrEqual($before - 0.000001, $captured);
        self::assertLessThanOrEqual($after + 0.000001, $captured);
        self::assertSame('UTC', $mars->earthTime()->getTimezone()->getName());
        self::assertSame(45.0, $mars->longitudeWest());
        self::assertSame(99.0, $mars->terrestrialTimeOffset());
    }

    private static function assertClockEqualsWithDelta(float $expected, float $actual, float $tolerance): void
    {
        self::assertTrue(is_finite($actual), 'Clock hours must be finite.');
        $difference = abs($actual - $expected);
        $difference = min($difference, abs(24.0 - $difference));

        self::assertEqualsWithDelta(0.0, $difference, $tolerance, 'Clock hours differ beyond the permitted tolerance.');
    }
}
