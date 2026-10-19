<?php

declare(strict_types=1);

namespace MartianTime;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use MartianTime\Internal\Cycle;
use MartianTime\Internal\TimeScale;

final readonly class MarsTime
{
    /**
     * Store the calculated values together so every reading describes the same instant.
     */
    private function __construct(
        private DateTimeImmutable $earthTime,
        private float $longitudeWest,
        private float $julianDateUtc,
        private float $julianDateTerrestrial,
        private float $terrestrialTimeOffset,
        private float $marsSolDate,
        private float $solarLongitudeDegrees,
        private float $equationOfTimeDegrees,
    ) {}

    /**
     * Calculate an immutable set of Martian time readings for an Earth instant.
     *
     * The input is copied into UTC without changing the original date. Its timezone
     * affects only how the Earth instant is expressed, not the calculated Mars time.
     *
     * Longitude is measured in degrees west: zero selects the prime meridian,
     * positive values select locations to its west, and negative values select
     * locations to its east. Values outside one revolution are wrapped automatically.
     *
     * The optional ttMinusUtc override is the difference between Terrestrial Time
     * and UTC in Earth SI seconds. Leaving it null selects the built-in historical
     * offsets or approximation. The result follows NASA's analytical Mars24 model.
     *
     * @throws InvalidArgumentException If longitude or an explicit TT offset is infinite or NaN.
     *
     * @see https://www.giss.nasa.gov/tools/mars24/help/algorithm.html
     * @see TimeScale::ttMinusUtc()
     */
    public static function fromDateTime(
        DateTimeInterface $earthTime,
        float $longitudeWest = 0.0,
        ?float $ttMinusUtc = null,
    ): self {
        if (!is_finite($longitudeWest)) {
            throw new InvalidArgumentException('Longitude must be finite.');
        }

        if ($ttMinusUtc !== null && !is_finite($ttMinusUtc)) {
            throw new InvalidArgumentException('TT minus UTC must be finite.');
        }

        $utc = DateTimeImmutable::createFromInterface($earthTime)->setTimezone(new DateTimeZone('UTC'));
        $julianUtc = TimeScale::julianDateUtc($utc);
        $offset = $ttMinusUtc ?? TimeScale::ttMinusUtc($utc);
        $julianTerrestrial = $julianUtc + $offset / 86_400.0;
        $daysSinceJ2000 = $julianTerrestrial - 2_451_545.0;

        // B-1 and B-2. Trigonometric functions below consume radians.
        $meanAnomaly = deg2rad(19.3871 + 0.52402073 * $daysSinceJ2000);
        $fictionMeanSun = 270.3871 + 0.524038496 * $daysSinceJ2000;

        // B-3. Planetary perturbations, in degrees.
        $perturbations = 0.0;
        foreach ([
            [0.0071, 2.2353, 49.409],
            [0.0057, 2.7543, 168.173],
            [0.0039, 1.1177, 191.837],
            [0.0037, 15.7866, 21.736],
            [0.0021, 2.1354, 15.704],
            [0.0020, 2.4694, 95.528],
            [0.0018, 32.8493, 49.095],
        ] as [$amplitude, $period, $phase]) {
            $perturbations += $amplitude * cos(deg2rad(0.985626 * $daysSinceJ2000 / $period + $phase));
        }

        // B-4 and B-5. Equation of center and areocentric solar longitude.
        $equationOfCenter = (10.691 + 3.0e-7 * $daysSinceJ2000) * sin($meanAnomaly)
            + 0.623 * sin(2.0 * $meanAnomaly)
            + 0.050 * sin(3.0 * $meanAnomaly)
            + 0.005 * sin(4.0 * $meanAnomaly)
            + 0.0005 * sin(5.0 * $meanAnomaly)
            + $perturbations;
        $solarLongitude = Cycle::wrap($fictionMeanSun + $equationOfCenter, 360.0);
        $solarRadians = deg2rad($solarLongitude);

        // C-1 and C-2. EOT is an angle; MSD is a continuous count of sols.
        $equationOfTime = 2.861 * sin(2.0 * $solarRadians)
            - 0.071 * sin(4.0 * $solarRadians)
            + 0.002 * sin(6.0 * $solarRadians)
            - $equationOfCenter;
        $marsSolDate = ($julianTerrestrial - 2_451_549.5) / 1.0274912517 + 44_796.0 - 0.0009626;

        return new self(
            $utc,
            Cycle::wrap($longitudeWest, 360.0),
            $julianUtc,
            $julianTerrestrial,
            $offset,
            $marsSolDate,
            $solarLongitude,
            $equationOfTime,
        );
    }

    /**
     * Capture the current Earth instant and calculate its Martian time readings.
     *
     * Longitude and the optional TT minus UTC override follow fromDateTime().
     * The returned object is a snapshot; call again to obtain an updated reading.
     *
     * @throws InvalidArgumentException If longitude or an explicit TT offset is infinite or NaN.
     *
     * @see self::fromDateTime()
     */
    public static function now(float $longitudeWest = 0.0, ?float $ttMinusUtc = null): self
    {
        return self::fromDateTime(new DateTimeImmutable('now', new DateTimeZone('UTC')), $longitudeWest, $ttMinusUtc);
    }

    /**
     * Retrieve the Earth instant used for these calculations, expressed in UTC.
     *
     * This immutable snapshot keeps the input's microseconds and remains unchanged
     * even if the caller later modifies the original mutable date.
     */
    public function earthTime(): DateTimeImmutable
    {
        return $this->earthTime;
    }

    /**
     * Retrieve the selected Martian longitude in degrees west, from zero up to 360.
     *
     * The upper limit is excluded: 360 degrees wraps to zero. An input of -15
     * degrees, meaning 15 degrees east, is stored as 345 degrees west.
     *
     * @see self::localMeanSolarTime()
     */
    public function longitudeWest(): float
    {
        return $this->longitudeWest;
    }

    /**
     * Retrieve the Earth instant as a fractional Julian day on the UTC/POSIX scale.
     *
     * Julian dates provide a continuous astronomical day count. For example,
     * 1970-01-01 at 00:00 UTC corresponds to 2440587.5; the fractional part is
     * measured from noon. PHP timestamps do not represent leap-second instants.
     *
     * @see self::julianDateTerrestrial()
     */
    public function julianDateUtc(): float
    {
        return $this->julianDateUtc;
    }

    /**
     * Retrieve the Earth instant as a Julian date on the Terrestrial Time scale.
     *
     * TT advances uniformly and supplies the time argument for the Mars orbital
     * calculations. This value includes the applied TT minus UTC correction,
     * converted from Earth seconds into fractions of an Earth day.
     *
     * @see self::terrestrialTimeOffset()
     */
    public function julianDateTerrestrial(): float
    {
        return $this->julianDateTerrestrial;
    }

    /**
     * Retrieve the applied Terrestrial Time minus UTC offset in Earth SI seconds.
     *
     * This is either the caller's explicit override or the value selected by the
     * built-in time-scale conversion. A positive offset means TT is ahead of UTC.
     * These seconds have their ordinary Earth duration, unlike Martian clock seconds.
     *
     * @see TimeScale::ttMinusUtc()
     */
    public function terrestrialTimeOffset(): float
    {
        return $this->terrestrialTimeOffset;
    }

    /**
     * Retrieve the continuous Mars Sol Date (MSD), including its fractional sol.
     *
     * A sol is a Martian solar day. MSD counts sols from a fixed reference epoch
     * shared across locations; it does not count sols since a particular mission
     * landed. Its fractional day determines the mean clock at the prime meridian.
     *
     * @see self::meanSolarTime()
     * @see https://www.giss.nasa.gov/tools/mars24/help/notes.html
     */
    public function marsSolDate(): float
    {
        return $this->marsSolDate;
    }

    /**
     * Retrieve the mean solar clock at Mars's prime meridian, independent of location.
     *
     * Also called Airy Mean Time or Coordinated Mars Time (MTC), this divides
     * the mean sol into 24 Martian hours. The reading repeats at each midnight;
     * use the Mars Sol Date when a continuous day count is also needed.
     *
     * @see self::marsSolDate()
     * @see self::localMeanSolarTime()
     */
    public function meanSolarTime(): TimeOfDay
    {
        return TimeOfDay::fromHours(24.0 * $this->marsSolDate);
    }

    /**
     * Retrieve prime-meridian mean solar time using the Coordinated Mars Time name.
     *
     * This is an alias of meanSolarTime() and returns the same clock reading.
     *
     * @see self::meanSolarTime()
     */
    public function coordinatedMarsTime(): TimeOfDay
    {
        return $this->meanSolarTime();
    }

    /**
     * Retrieve local mean solar time (LMST) at the selected Martian longitude.
     *
     * Each 15 degrees west moves the clock one Martian hour behind the prime
     * meridian. The reading wraps across midnight into the 24-hour clock range.
     *
     * @see self::longitudeWest()
     * @see self::localTrueSolarTime()
     * @see https://www.giss.nasa.gov/tools/mars24/help/algorithm.html Section C-3.
     */
    public function localMeanSolarTime(): TimeOfDay
    {
        return TimeOfDay::fromHours($this->meanSolarTime()->hours() - $this->longitudeWest / 15.0);
    }

    /**
     * Retrieve local true solar time (LTST), the clock corresponding to the actual Sun.
     *
     * This adds the seasonally varying equation of time to the local mean clock.
     * A positive correction puts true time ahead of mean time. The final reading
     * wraps across midnight into the 24-hour clock range.
     *
     * @see self::equationOfTimeHours()
     * @see https://www.giss.nasa.gov/tools/mars24/help/algorithm.html Section C-4.
     */
    public function localTrueSolarTime(): TimeOfDay
    {
        return TimeOfDay::fromHours($this->localMeanSolarTime()->hours() + $this->equationOfTimeHours());
    }

    /**
     * Retrieve areocentric solar longitude (Ls), an angle describing the Martian season.
     *
     * The angle runs from zero up to, but excluding, 360 degrees. Northern spring,
     * summer, autumn, and winter begin at 0, 90, 180, and 270 degrees respectively.
     * This seasonal angle is separate from the selected surface longitude.
     *
     * @see https://www.giss.nasa.gov/tools/mars24/help/notes.html
     */
    public function solarLongitudeDegrees(): float
    {
        return $this->solarLongitudeDegrees;
    }

    /**
     * Retrieve the signed difference between true and mean solar time as an angle.
     *
     * Mars's orbital shape and axial tilt make this correction vary with season.
     * Fifteen degrees correspond to one Martian hour; use equationOfTimeHours()
     * to obtain the correction in the same units as the clock readings.
     *
     * @see self::equationOfTimeHours()
     */
    public function equationOfTimeDegrees(): float
    {
        return $this->equationOfTimeDegrees;
    }

    /**
     * Retrieve the signed correction from mean to true solar time in Martian hours.
     *
     * Add this value to local mean solar time to obtain local true solar time,
     * wrapping the sum at midnight. A value of 0.5 means true time is 30 Martian
     * minutes ahead of mean time. The correction itself retains its sign.
     *
     * @see self::localMeanSolarTime()
     * @see self::localTrueSolarTime()
     */
    public function equationOfTimeHours(): float
    {
        return $this->equationOfTimeDegrees / 15.0;
    }
}
