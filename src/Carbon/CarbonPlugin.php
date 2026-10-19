<?php

declare(strict_types=1);

namespace MartianTime\Carbon;

use MartianTime\MarsTime;

final class CarbonPlugin
{
    /** @var list<class-string> */
    private const CARBON_CLASSES = [
        'Carbon\\Carbon',
        'Carbon\\CarbonImmutable',
    ];

    private function __construct() {}

    /**
     * Check whether either supported Carbon date class is available.
     *
     * Detection invokes registered PHP autoloaders, so Carbon can be found
     * before the application has used it. This does not register macros;
     * the core library can be used without installing Carbon.
     *
     * @see self::register()
     */
    public static function isAvailable(): bool
    {
        foreach (self::CARBON_CLASSES as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enable the toMarsTime() instance method on each available Carbon date class.
     *
     * Call explicitly during application setup to add a Carbon macro, a method
     * defined at runtime, to mutable Carbon\Carbon and immutable
     * Carbon\CarbonImmutable instances. Calling $date->toMarsTime() then returns
     * a MarsTime value for that instant without changing the original date.
     *
     * The macro accepts optional longitudeWest in degrees west of the Martian
     * prime meridian, with negative values for east and a default of 0. Its
     * optional ttMinusUtc is Terrestrial Time minus UTC in Earth SI seconds;
     * null calculates the offset for the date, while an explicit 0 is retained.
     *
     * Existing methods and macros named toMarsTime are preserved, and repeated
     * registration is safe. A true result means at least one Carbon date class
     * was available, even if its existing method or macro required no changes.
     * False means neither supported Carbon date class could be found.
     *
     * @see self::isAvailable()
     * @see MarsTime::fromDateTime()
     * @see https://carbonphp.github.io/carbon/guide/advanced-features/macro.html Carbon macro guide.
     */
    public static function register(): bool
    {
        $available = false;

        foreach (self::CARBON_CLASSES as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $available = true;

            if (method_exists($class, 'toMarsTime') || $class::hasMacro('toMarsTime')) {
                continue;
            }

            $class::macro('toMarsTime', /**
             * Convert the Carbon instance to Martian time without changing it.
             *
             * Longitude is measured in degrees west; the optional TT-UTC offset
             * is measured in Earth SI seconds. Both follow the core factory's
             * defaults and validation rules.
             *
             * @see MarsTime::fromDateTime()
             *
             * @throws \InvalidArgumentException If longitude or an explicit TT-UTC offset is not finite.
             */ function (
                float $longitudeWest = 0.0,
                ?float $ttMinusUtc = null,
            ): MarsTime {
                return MarsTime::fromDateTime($this, $longitudeWest, $ttMinusUtc);
            });
        }

        return $available;
    }
}
