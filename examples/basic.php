<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MartianTime\MarsTime;

// NASA's near-coincident Earth/Mars midnight example, at the prime meridian.
$earthTime = new DateTimeImmutable('2000-01-06T00:00:00Z');
$marsTime = MarsTime::fromDateTime($earthTime);

printf("Earth UTC: %s\n", $marsTime->earthTime()->format('Y-m-d H:i:s T'));
printf("Mars Sol Date: %.8f\n", $marsTime->marsSolDate());
printf("Prime-meridian mean solar time: %s\n", $marsTime->meanSolarTime());
printf("Local mean solar time: %s\n", $marsTime->localMeanSolarTime());
printf("Local true solar time: %s\n", $marsTime->localTrueSolarTime());
printf("Solar longitude: %.5f degrees\n", $marsTime->solarLongitudeDegrees());
