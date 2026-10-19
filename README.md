# Martian Time

A small PHP library for Martian solar time, based on the [NASA GISS Mars24 algorithm and worked examples](https://www.giss.nasa.gov/tools/mars24/help/algorithm.html), updated January 7, 2025.

## Compatibility

PHP 8.2, 8.3, 8.4, and 8.5 are the [supported PHP branches](https://www.php.net/supported-versions.php) as of October 19, 2026. The package targets all four, with a PHP `^8.2` constraint and a CI matrix covering each branch. Future PHP 8.x releases are permitted by the constraint but need verification when released.

Optional integration supports Carbon `^2.73 || ^3.0`, including both `Carbon\Carbon` and `Carbon\CarbonImmutable`. Carbon is a development dependency here and an optional dependency in consuming applications. The package's runtime requirements contain only PHP.

## Installation and local setup

The package has not been published yet. After publication, install it in an application with:

```sh
composer require oliverearl/martian-time
```

For the local checkout, install its development tools and run the checks:

```sh
composer install
composer check
php examples/basic.php
```

Composer provides autoloading. The calculation code itself needs only PHP, so it can also be used with an application's PSR-4 loader mapping `MartianTime\` to `src/`.

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use MartianTime\MarsTime;

$earthTime = new DateTimeImmutable('2000-01-06T00:00:00Z');
$mars = MarsTime::fromDateTime($earthTime);

echo $mars->meanSolarTime()->format(); // 23:59:39
echo $mars->marsSolDate();             // Continuous count of Martian sols

// NASA uses degrees west: Curiosity's 137.4 degrees east is 222.6 degrees west.
$curiosity = MarsTime::fromDateTime($earthTime, longitudeWest: 222.6);
echo $curiosity->localMeanSolarTime();
echo $curiosity->localTrueSolarTime();

$current = MarsTime::now(longitudeWest: 0.0);
```

`fromDateTime()` accepts any `DateTimeInterface`, including mutable and immutable PHP dates and Carbon dates. It snapshots the instant in UTC. Input timezone offsets therefore do not change the result for the same instant.

A sol is divided into 24 Martian hours, each with 60 minutes of 60 seconds. These units are longer than their Earth equivalents. The returned clock readings describe Martian solar time; `earthTime()` remains an Earth UTC timestamp.

## API

Create an immutable result with:

```php
MarsTime::fromDateTime(
    DateTimeInterface $earthTime,
    float $longitudeWest = 0.0,
    ?float $ttMinusUtc = null,
): MarsTime;

MarsTime::now(
    float $longitudeWest = 0.0,
    ?float $ttMinusUtc = null,
): MarsTime;
```

| Method | Result |
| --- | --- |
| `earthTime()` | UTC `DateTimeImmutable` snapshot of the input instant |
| `julianDateUtc()` | Earth Julian date using the UTC/POSIX timestamp |
| `julianDateTerrestrial()` | Earth Julian date in Terrestrial Time (TT) |
| `terrestrialTimeOffset()` | Applied TT minus UTC offset, in Earth seconds |
| `marsSolDate()` | Continuous Mars Sol Date (MSD) |
| `meanSolarTime()` | Prime-meridian mean solar time as `TimeOfDay` |
| `coordinatedMarsTime()` | Alias of `meanSolarTime()` |
| `localMeanSolarTime()` | Mean solar time at the selected longitude, as `TimeOfDay` |
| `localTrueSolarTime()` | Local mean time adjusted by the equation of time, as `TimeOfDay` |
| `solarLongitudeDegrees()` | Areocentric solar longitude, Ls, in degrees |
| `equationOfTimeDegrees()` | Signed equation of time, in degrees |
| `equationOfTimeHours()` | Signed equation of time, in Martian hours |
| `longitudeWest()` | Selected longitude normalized to [0, 360), in degrees west |

`TimeOfDay::fromHours(float $hours)` creates a clock value normalized to a 24-hour cycle. `hours()` returns decimal hours; `format()` and string conversion return `HH:MM:SS`, discarding fractional seconds. A clock value contains no calendar date or mission-sol number.

## Optional Carbon plugin

Install Carbon in the consuming application if needed, then register the plugin after loading the application's autoloader:

```php
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use MartianTime\Carbon\CarbonPlugin;

if (CarbonPlugin::isAvailable()) {
    CarbonPlugin::register();

    $mars = Carbon::parse('2000-01-06T00:00:00Z')->toMarsTime();
    $local = CarbonImmutable::now('UTC')->toMarsTime(longitudeWest: 222.6);
}
```

`isAvailable()` detects installed Carbon classes. `register()` returns `false` when Carbon is absent, and installs the `toMarsTime($longitudeWest = 0.0, $ttMinusUtc = null)` macro on available Carbon classes. Registration is repeatable and preserves any existing macro with that name. Autoloading this library does not register macros or change Carbon's global configuration.

The macro returns `MarsTime`; its calculations use the same core as `fromDateTime()`.

## Time scales and accuracy

The implementation independently follows Mars24 sections A–C: Julian dates, UTC to TT conversion, orbital parameters, MSD, prime-meridian mean solar time, and local mean/true solar time. It is an analytical approximation rather than a numerical ephemeris.

For dates from January 1, 1972, the built-in table applies historical TAI minus UTC offsets and adds 32.184 seconds to obtain TT minus UTC. The last change in the table is January 1, 2017, giving TT minus UTC of 69.184 seconds. [IERS Bulletin C 72](https://hpiers.obspm.fr/iers/bul/bulc/bulletinc.72) confirms there is no leap second at the end of December 2026. For earlier dates, the library follows NASA's empirical polynomial; this is approximate historical time-scale handling.

Future dates continue using the last known offset. Update the table when a leap second is announced, or supply `ttMinusUtc` explicitly, in Earth seconds:

```php
$mars = MarsTime::fromDateTime(
    $earthTime,
    longitudeWest: 0.0,
    ttMinusUtc: 69.184,
);
```

PHP timestamps do not represent leap-second instants such as `23:59:60`; those inputs are unsupported. No time-scale data is fetched at runtime. Formatting truncates fractional Martian seconds and does not imply subsecond physical accuracy.

## Development and verification

Install the development dependencies with `composer install`, then use:

| Command | Purpose |
| --- | --- |
| `composer test` | Run all PHPUnit suites, including NASA's worked examples and Carbon integration |
| `composer test:unit` | Run the core unit suite |
| `composer test:carbon` | Run the Carbon integration suite |
| `composer lint` | Check formatting with Laravel Pint |
| `composer lint:fix` | Apply Pint formatting |
| `composer analyse` | Run PHPStan at its configured maximum level |
| `composer check` | Validate Composer metadata, formatting, static analysis, and tests |

Pint uses its PER coding-style preset. PHPStan analyses the runtime source and example at maximum level, targeting PHP 8.2 syntax and types.

CI resolves dependencies independently for PHP 8.2–8.5 and Carbon `^2.73` / `^3.0`, then runs all PHPUnit suites. A separate quality job checks the manifest, formatting, static analysis, and the basic example. Local dependency resolution targets PHP 8.2; CI resolves dependencies against each PHP runtime.
