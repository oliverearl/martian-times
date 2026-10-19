<?php

declare(strict_types=1);

namespace MartianTime;

use InvalidArgumentException;
use MartianTime\Internal\Cycle;
use Stringable;

final readonly class TimeOfDay implements Stringable
{
    private function __construct(private float $hours) {}

    /**
     * Create a clock reading from decimal Martian hours.
     *
     * A Martian solar day, called a sol, is divided into 24 Martian hours,
     * each with 60 minutes of 60 seconds. A sol lasts longer than an Earth
     * day, so these clock units are longer than their Earth equivalents.
     *
     * Values wrap around midnight: -1 becomes 23, 24 becomes 0, and 25.5
     * becomes 1.5. Only the position within a sol is retained; this object
     * does not store a calendar date or count how many sols have elapsed.
     *
     * @see self::hours()
     * @see self::format()
     *
     * @throws InvalidArgumentException If the input is NaN or positive or negative infinity.
     */
    public static function fromHours(float $hours): self
    {
        if (!is_finite($hours)) {
            throw new InvalidArgumentException('Clock hours must be finite.');
        }

        return new self(Cycle::wrap($hours, 24.0));
    }

    /**
     * Read decimal hours since Martian midnight, from 0 inclusive to 24 exclusive.
     *
     * For example, 12.5 represents 12 Martian hours and 30 Martian minutes.
     * The fractional part is retained for calculations and finer precision.
     *
     * @see self::format()
     */
    public function hours(): float
    {
        return $this->hours;
    }

    /**
     * Render a zero-padded Martian clock reading as HH:MM:SS.
     *
     * Fractional Martian seconds are discarded: a reading of 12:34:56.9 is
     * displayed as 12:34:56. The value is truncated, so a fraction never
     * rounds the displayed time into the next minute or day.
     *
     * @see self::hours()
     */
    public function format(): string
    {
        $seconds = (int) floor($this->hours * 3_600.0);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3_600),
            intdiv($seconds % 3_600, 60),
            $seconds % 60,
        );
    }

    /**
     * Use the formatted clock reading when cast or interpolated as a string.
     *
     * @see self::format()
     */
    public function __toString(): string
    {
        return $this->format();
    }
}
