<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Scheduler\Locator\ScheduleConfigLocatorInterface;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;

class DummyScheduleConfigLocator implements ScheduleConfigLocatorInterface
{
    /**
     * @var array<string, ScheduleConfig>
     */
    public static array $schedules = [];

    public function get(string $id): ScheduleConfig
    {
        if (isset(static::$schedules[$id])) {
            return static::$schedules[$id];
        }

        throw new class(sprintf('You have requested a non-existent schedule "%s".', $id)) extends \InvalidArgumentException implements NotFoundExceptionInterface { };
    }

    public function has(string $id): bool
    {
        return isset(static::$schedules[$id]);
    }
}
