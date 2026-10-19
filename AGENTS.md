# Working on Martian Time

## Project and API

`oliverearl/martian-time` is an MIT-licensed PHP library using the `MartianTime` namespace. Keep it compatible with PHP 8.2 and later PHP 8.x releases. Its runtime requires only PHP; Carbon and the quality tools are development dependencies.

Start with [README.md](README.md) for usage and the public API. The main entry points are `MarsTime::fromDateTime()` and `MarsTime::now()`. Results are immutable snapshots. Clock methods return `TimeOfDay`; use `hours()` for decimal Martian hours and `format()` for `HH:MM:SS`.

| Location | Responsibility |
| --- | --- |
| `src/MarsTime.php` | NASA Mars24 calculations and public time readings |
| `src/TimeOfDay.php` | Normalized Martian clock values and formatting |
| `src/Internal/TimeScale.php` | Julian dates, UTC-to-TT conversion, leap-second data |
| `src/Internal/Cycle.php` | Positive floating-point cycle wrapping |
| `src/Carbon/CarbonPlugin.php` | Optional, explicitly registered Carbon macros |
| `tests/Unit/`, `tests/Integration/` | Calculation tests and Carbon integration tests |

## Calculation conventions

- Follow the [NASA Mars24 algorithm](https://www.giss.nasa.gov/tools/mars24/help/algorithm.html). Use its worked examples when changing calculations; published intermediate values are rounded, so preserve the existing test tolerances.
- Longitude is **west-positive**, in degrees. Negative values represent east longitude. Keep angles and clocks normalized using floating-point wrapping; PHP's integer `%` operator is unsuitable here.
- A sol is a Martian solar day, divided into 24 Martian hours. These clock units are longer than Earth units. Mars Sol Date is a continuous reference count, not a mission's sol number or a calendar date.
- Mean solar time describes an evenly advancing clock. True solar time adds the signed equation of time. Keep degrees, Martian hours, and Earth SI seconds distinct.
- Snapshot `DateTimeInterface` inputs in UTC without mutating them. Preserve microseconds, including before the Unix epoch: combine `format('U')` and the positive microsecond fraction separately, rather than casting `format('U.u')`.
- TT minus UTC overrides are in Earth SI seconds. `null` selects the built-in conversion; an explicit `0.0` is valid and must remain effective.
- Preserve the pre-1972 approximation and historical leap-second boundaries. Verify changes against [IERS data](https://hpiers.obspm.fr/iers/bul/bulc/Leap_Second.dat). Future dates use the last known offset; do not fetch time-scale data at runtime.
- Retain full calculation precision until display. `TimeOfDay::format()` discards fractional Martian seconds rather than rounding.

## Optional Carbon integration

Support both `Carbon\Carbon` and `Carbon\CarbonImmutable`, with Carbon `^2.73 || ^3.0`. Loading the library or detecting Carbon must not register macros. Applications explicitly call `CarbonPlugin::register()` to enable `toMarsTime()`.

Preserve existing application methods and macros, allow repeated registration, and never mutate the supplied Carbon date. Carbon 3 shares its macro registry between mutable and immutable classes; integration tests must clean up only this library's macro at test boundaries.

## Development commands

Run from the repository root:

```sh
composer install
composer check
php examples/basic.php
```

`composer check` validates Composer metadata and runs Pint, PHPStan, and PHPUnit. Individual commands are:

- `composer lint` / `composer lint:fix`: check or apply Pint's PER preset.
- `composer analyse`: PHPStan at maximum level for `src/` and `examples/`.
- `composer test`, `composer test:unit`, `composer test:carbon`: all PHPUnit suites, core tests, or Carbon tests.

Use the existing PHPUnit 11.5 baseline for PHP 8.2 compatibility. Local Composer dependency resolution targets PHP 8.2.0. CI deliberately removes that override and tests each configured PHP runtime against Carbon 2 and 3; do not copy CI's manifest changes into the local configuration.

If a sandbox blocks PHPStan's localhost worker socket, use `php vendor/bin/phpstan analyse --debug --memory-limit=512M` for a serial analysis run. Keep the normal Composer command intact.

## Change and documentation standards

- Use strict types, explicit type hints, immutable values, and the existing small API. Keep runtime dependencies absent and avoid adding framework or tooling infrastructure without a concrete need.
- Write helpful method docblocks explaining units, conventions, defaults, and behavior. Avoid class-level docblocks unless necessary. Do not repeat type hints with `@param` or `@return`; use annotations when generics or array shapes add information. Use `@see` for references and `@throws` for actual exceptions.
- Preserve the user's edits, especially the shortened README. Make focused documentation updates without restoring removed prose.
- Test calculation changes and observable Carbon behavior with PHPUnit. Do not add subprocess-based tests, process-isolation attributes, custom autoloaders, or tests of Composer's loading behavior. Development environments may prohibit process execution.
- Run checks appropriate to the change. Documentation-only edits need reference and whitespace checks; source changes should pass `composer check`. Do not weaken numerical tolerances or add static-analysis suppressions just to make checks pass.

## Repository and distribution

- Put scratch files and temporary fixtures in ignored `work/`. Keep `vendor/`, editor files, tool caches, and `composer.lock` out of commits. Composer can generate a local lockfile during development; it does not belong in this repository or its distributions.
- Keep `.gitattributes` export rules and `composer.json` archive exclusions consistent. Tests, examples, agent instructions, and development configuration are excluded from distribution archives. Source checkouts still contain repository files.
- Do not restore the removed distribution-check script or `check:dist` command.
- Keep commits atomic by feature and sign them using the configured signing key. When committing several new feature groups, put bootstrap files first and testing/CI files last. Never fall back to unsigned commits if signing fails.
