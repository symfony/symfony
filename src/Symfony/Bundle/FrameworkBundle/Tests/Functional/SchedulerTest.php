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
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummySchedule;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Symfony\Component\Scheduler\RecurringMessage;

class SchedulerTest extends AbstractWebTestCase
{
    public function testScheduler()
    {
        $scheduledMessages = [
            RecurringMessage::every('5 minutes', $foo = new FooMessage(), new \DateTimeImmutable('2020-01-01T00:00:00Z')),
            RecurringMessage::every('5 minutes', $bar = new BarMessage(), new \DateTimeImmutable('2020-01-01T00:01:00Z')),
        ];
        DummySchedule::$recurringMessages = $scheduledMessages;

        $container = self::getContainer();
        $container->set('clock', $clock = new MockClock('2020-01-01T00:09:59Z'));

        $this->assertTrue($container->get('receivers')->has('scheduler_dummy'));
        $this->assertInstanceOf(SchedulerTransport::class, $cron = $container->get('receivers')->get('scheduler_dummy'));

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
        $this->assertSame([$foo, $bar, $foo, $bar], $fetchMessages(600.0));
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'Scheduler'] + $options);
    }
}
