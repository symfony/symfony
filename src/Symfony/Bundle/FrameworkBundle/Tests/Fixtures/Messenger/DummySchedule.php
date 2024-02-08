<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('dummy')]
class DummySchedule implements ScheduleProviderInterface
{
    public static array $recurringMessages;

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(...self::$recurringMessages)
            ->stateful(new ArrayAdapter())
            ->lock(new Lock(new Key('dummy'), new InMemoryStore()))
        ;
    }
}
