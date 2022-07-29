<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyScheduleConfigLocator;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Scheduler\Messenger\ScheduleTransport;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

class SchedulerTest extends AbstractWebTestCase
{
    public function testScheduler()
    {
        $scheduleConfig = new ScheduleConfig([
            [PeriodicalTrigger::create(600, '2020-01-01T00:00:00Z'), $foo = new FooMessage()],
            [PeriodicalTrigger::create(600, '2020-01-01T00:01:00Z'), $bar = new BarMessage()],
        ]);
        DummyScheduleConfigLocator::$schedules = ['default' => $scheduleConfig];

        $container = self::getContainer();
        $container->set('clock', $clock = new MockClock('2020-01-01T00:09:59Z'));

        $this->assertTrue($container->get('receivers')->has('schedule'));
        $this->assertInstanceOf(ScheduleTransport::class, $cron = $container->get('receivers')->get('schedule'));

        $fetchMessages = static function (float $sleep) use ($clock, $cron) {
            if (0 < $sleep) {
                $clock->sleep($sleep);
            }
            $messages = [];
            foreach ($cron->get() as $key => $envelope) {
                $messages[$key] = $envelope->getMessage();
            }

            return $messages;
        };

        $this->assertSame([], $fetchMessages(0.0));
        $this->assertSame([$foo], $fetchMessages(1.0));
        $this->assertSame([], $fetchMessages(1.0));
        $this->assertSame([$bar], $fetchMessages(60.0));
        $this->assertSame([$foo, $bar], $fetchMessages(600.0));
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'Scheduler'] + $options);
    }
}
