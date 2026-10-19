<?php

declare(strict_types=1);

namespace MartianTime\Tests\Unit;

use InvalidArgumentException;
use MartianTime\TimeOfDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeOfDayTest extends TestCase
{
    #[DataProvider('clockReadings')]
    public function testClockHoursAreNormalizedAndFormattedByFlooring(float $hours, string $expected): void
    {
        self::assertSame($expected, TimeOfDay::fromHours($hours)->format());
    }

    /** @return iterable<string, array{float, string}> */
    public static function clockReadings(): iterable
    {
        yield 'midnight' => [0.0, '00:00:00'];
        yield 'full day' => [24.0, '00:00:00'];
        yield 'negative fraction' => [-0.25, '23:45:00'];
        yield 'more than two days' => [49.5, '01:30:00'];
        yield 'fractional second before midnight' => [24.0 - 0.25 / 3600.0, '23:59:59'];
        yield 'fractional second after midnight' => [24.0 + 1.5 / 3600.0, '00:00:01'];
    }

    public function testStringConversion(): void
    {
        self::assertSame('12:30:00', (string) TimeOfDay::fromHours(12.5));
    }

    public function testNegativeMultipleDayHours(): void
    {
        self::assertSame(23.75, TimeOfDay::fromHours(-48.25)->hours());
    }

    #[DataProvider('nonFiniteHours')]
    public function testNonFiniteClockHoursAreRejected(float $hours): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeOfDay::fromHours($hours);
    }

    /** @return iterable<string, array{float}> */
    public static function nonFiniteHours(): iterable
    {
        yield 'positive infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'not a number' => [NAN];
    }
}
