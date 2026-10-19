<?php

declare(strict_types=1);

namespace MartianTime\Tests\Integration;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use MartianTime\Carbon\CarbonPlugin;
use MartianTime\MarsTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CarbonPluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearMarsTimeMacros();
    }

    protected function tearDown(): void
    {
        $this->clearMarsTimeMacros();
        parent::tearDown();
    }

    /** @return array<string, array{class-string<Carbon|CarbonImmutable>}> */
    public static function carbonClasses(): array
    {
        return [
            'mutable Carbon' => [Carbon::class],
            'immutable Carbon' => [CarbonImmutable::class],
        ];
    }

    /** @return array<string, array{class-string<Carbon|CarbonImmutable>, float, ?float}> */
    public static function conversionInputs(): array
    {
        $cases = [];

        foreach (self::carbonClasses() as $name => [$class]) {
            $cases[$name . ', west longitude and automatic TT-UTC'] = [$class, 137.4, null];
            $cases[$name . ', west longitude and zero TT-UTC'] = [$class, 137.4, 0.0];
            $cases[$name . ', east longitude and custom TT-UTC'] = [$class, -137.4, 70.0];
        }

        return $cases;
    }

    public function testDetectingCarbonLeavesMacroRegistrationToTheApplication(): void
    {
        self::assertTrue(CarbonPlugin::isAvailable());
        self::assertFalse(Carbon::hasMacro('toMarsTime'));
        self::assertFalse(CarbonImmutable::hasMacro('toMarsTime'));
    }

    #[DataProvider('carbonClasses')]
    public function testConvertsTheInstantWithoutMutatingTheInput(string $class): void
    {
        self::assertTrue(CarbonPlugin::register());
        self::assertTrue($class::hasMacro('toMarsTime'));

        $date = new $class('2020-02-18 16:45:12.123456', new DateTimeZone('Pacific/Auckland'));
        $original = $date->format('Y-m-d H:i:s.u e P');
        $expected = MarsTime::fromDateTime(DateTimeImmutable::createFromInterface($date));
        $actual = $date->toMarsTime();

        self::assertInstanceOf(MarsTime::class, $actual);
        self::assertEqualsWithDelta($expected->marsSolDate(), $actual->marsSolDate(), 1.0e-12);
        self::assertEqualsWithDelta($expected->meanSolarTime()->hours(), $actual->meanSolarTime()->hours(), 1.0e-12);
        self::assertSame($expected->earthTime()->format('Y-m-d H:i:s.u e P'), $actual->earthTime()->format('Y-m-d H:i:s.u e P'));
        self::assertSame($original, $date->format('Y-m-d H:i:s.u e P'));
    }

    #[DataProvider('conversionInputs')]
    public function testForwardsWestLongitudeAndTerrestrialTimeOffset(
        string $class,
        float $longitudeWest,
        ?float $ttMinusUtc,
    ): void {
        CarbonPlugin::register();
        $date = new $class('2020-02-18 16:45:12.123456', new DateTimeZone('Pacific/Auckland'));
        $expected = MarsTime::fromDateTime($date, $longitudeWest, $ttMinusUtc);
        $actual = $date->toMarsTime($longitudeWest, $ttMinusUtc);

        self::assertSame($expected->longitudeWest(), $actual->longitudeWest());
        self::assertSame($expected->terrestrialTimeOffset(), $actual->terrestrialTimeOffset());
        self::assertEqualsWithDelta($expected->marsSolDate(), $actual->marsSolDate(), 1.0e-12);
        self::assertEqualsWithDelta($expected->localMeanSolarTime()->hours(), $actual->localMeanSolarTime()->hours(), 1.0e-12);
        self::assertEqualsWithDelta($expected->localTrueSolarTime()->hours(), $actual->localTrueSolarTime()->hours(), 1.0e-12);
    }

    public function testRepeatedRegistrationPreservesTheRegisteredMacros(): void
    {
        self::assertTrue(CarbonPlugin::register());
        $mutableMacro = Carbon::getMacro('toMarsTime');
        $immutableMacro = CarbonImmutable::getMacro('toMarsTime');

        self::assertNotNull($mutableMacro);
        self::assertNotNull($immutableMacro);
        self::assertTrue(CarbonPlugin::register());
        self::assertSame($mutableMacro, Carbon::getMacro('toMarsTime'));
        self::assertSame($immutableMacro, CarbonImmutable::getMacro('toMarsTime'));
    }

    #[DataProvider('carbonClasses')]
    public function testPreservesAnExistingApplicationMacro(string $class): void
    {
        $existingMacro = function (): string {
            return 'application-owned macro';
        };
        $class::macro('toMarsTime', $existingMacro);

        self::assertTrue(CarbonPlugin::register());
        self::assertSame($existingMacro, $class::getMacro('toMarsTime'));
        self::assertSame('application-owned macro', (new $class('2020-01-01'))->toMarsTime());
    }

    private function clearMarsTimeMacros(): void
    {
        // Carbon 3 shares the registry between mutable and immutable classes.
        // Clear only this plugin's macro at test boundaries, after assertions.
        Carbon::macro('toMarsTime', null);
        CarbonImmutable::macro('toMarsTime', null);
    }
}
