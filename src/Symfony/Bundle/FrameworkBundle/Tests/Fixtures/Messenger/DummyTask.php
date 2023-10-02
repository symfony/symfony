<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCronTask(expression: '* * * * *', arguments: [1], schedule: 'dummy_task')]
#[AsCronTask(expression: '0 * * * *', timezone: 'Europe/Berlin', arguments: ['2'], schedule: 'dummy_task', method: 'method2')]
#[AsPeriodicTask(frequency: 5, arguments: [3], schedule: 'dummy_task')]
#[AsPeriodicTask(frequency: '1 day', from: '00:00:00', jitter: 60, arguments: ['4'], schedule: 'dummy_task', method: 'method4')]
class DummyTask
{
    public static array $calls = [];

    #[AsPeriodicTask(frequency: '1 hour', from: '09:00:00', until: '17:00:00', arguments: ['b' => 6, 'a' => '5'], schedule: 'dummy_task')]
    #[AsCronTask(expression: '0 0 * * *', arguments: ['7', 8], schedule: 'dummy_task')]
    public function attributesOnMethod(string $a, int $b): void
    {
        self::$calls[__FUNCTION__][] = [$a, $b];
    }

    public function __call(string $name, array $arguments)
    {
        self::$calls[$name][] = $arguments;
    }
}
