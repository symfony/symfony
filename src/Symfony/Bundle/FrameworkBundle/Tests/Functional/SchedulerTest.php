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
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyCommand;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummySchedule;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyTask;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
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

    public function testAutoconfiguredScheduler()
    {
        $container = self::getContainer();
        $container->set('clock', $clock = new MockClock('2023-10-26T08:59:59Z'));

        $this->assertTrue($container->get('receivers')->has('scheduler_dummy_task'));
        $this->assertInstanceOf(SchedulerTransport::class, $cron = $container->get('receivers')->get('scheduler_dummy_task'));
        $bus = $container->get(MessageBusInterface::class);

        $getCalls = static function (float $sleep) use ($clock, $cron, $bus) {
            DummyTask::$calls = [];
            $clock->sleep($sleep);
            foreach ($cron->get() as $message) {
                $bus->dispatch($message->with(new ReceivedStamp('scheduler_dummy_task')));
            }

            return DummyTask::$calls;
        };

        $this->assertSame([], $getCalls(0));
        $this->assertSame(['__invoke' => [[1]], 'method2' => [['2']], 'attributesOnMethod' => [['5', 6]]], $getCalls(1));
        $this->assertSame(['__invoke' => [[3]]], $getCalls(5));
        $this->assertSame(['__invoke' => [[3]]], $getCalls(5));
        $calls = $getCalls(3595);
        $this->assertCount(779, $calls['__invoke']);
        $this->assertSame([['2']], $calls['method2']);
        $this->assertSame([['4']], $calls['method4']);
        $this->assertSame([['9'], ['9b'], ['named' => '9']], $calls['method5']);
        $this->assertSame([['5', 6], ['7', 8]], $calls['attributesOnMethod']);
    }

    public function testAutoconfiguredSchedulerCommand()
    {
        $container = self::getContainer();
        $container->set('clock', $clock = new MockClock('2023-10-26T08:59:59Z'));

        $this->assertTrue($container->get('receivers')->has('scheduler_dummy_command'));
        $this->assertInstanceOf(SchedulerTransport::class, $cron = $container->get('receivers')->get('scheduler_dummy_command'));
        $bus = $container->get(MessageBusInterface::class);

        $getCalls = static function (float $sleep) use ($clock, $cron, $bus) {
            DummyCommand::$calls = [];
            $clock->sleep($sleep);
            foreach ($cron->get() as $message) {
                $bus->dispatch($message->with(new ReceivedStamp('scheduler_dummy_command')));
            }

            return DummyCommand::$calls;
        };

        $this->assertSame([], $getCalls(0));
        $this->assertSame(['execute' => [0 => null, 1 => 'test']], $getCalls(1));
    }

    public function testSchedulerWithCustomTransport()
    {
        $container = self::getContainer();
        $container->set('clock', new MockClock('2023-10-26T08:59:59Z'));

        $this->assertTrue($container->get('receivers')->has('scheduler_custom_receiver'));
        $this->assertSame($container->get('scheduler_custom_receiver'), $container->get('receivers')->get('scheduler_custom_receiver'));
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'Scheduler'] + $options);
    }
}
