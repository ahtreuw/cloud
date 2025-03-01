<?php declare(strict_types=1);

namespace Vulpes\Cloud\Clock;

use DateTimeImmutable;
use DateTimeZone;

use Psr\Clock\ClockInterface;

class Clock implements ClockInterface
{
    public const string  NOW = 'now';
    public const string  UTC = 'UTC';


    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW, new DateTimeZone(self::UTC));
    }
}
