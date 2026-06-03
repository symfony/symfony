<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Symfony\Component\Scheduler\Messenger\SchedulerTransportFactory;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Tests\Fixtures\SomeScheduleProvider;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class SchedulerTransportFactoryTest extends TestCase
{
    public function testCreateTransport()
    {
        $trigger = $this->createStub(TriggerInterface::class);
        $serializer = $this->createStub(SerializerInterface::class);
        $clock = new MockClock();

        $defaultRecurringMessage = RecurringMessage::trigger($trigger, (object) ['id' => 'default']);
        $customRecurringMessage = RecurringMessage::trigger($trigger, (object) ['id' => 'custom']);

        $default = new SchedulerTransport(new MessageGenerator(new SomeScheduleProvider([$defaultRecurringMessage]), 'default', $clock));
        $custom = new SchedulerTransport(new MessageGenerator(new SomeScheduleProvider([$customRecurringMessage]), 'custom', $clock));

        $factory = new SchedulerTransportFactory(
            new Container([
                'default' => static fn () => new SomeScheduleProvider([$defaultRecurringMessage]),
                'custom' => static fn () => new SomeScheduleProvider([$customRecurringMessage]),
            ]),
            $clock,
        );

        $this->assertEquals($default, $factory->createTransport('schedule://default', [], $serializer));
        $this->assertEquals($custom, $factory->createTransport('schedule://custom', ['cache' => 'app'], $serializer));
    }

    public function testInvalidDsn()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Schedule DSN "schedule://#wrong" is invalid.');

        $factory->createTransport('schedule://#wrong', [], $this->createStub(SerializerInterface::class));
    }

    public function testNoName()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Schedule DSN must contains a name, e.g. "schedule://default".');

        $factory->createTransport('schedule://', [], $this->createStub(SerializerInterface::class));
    }

    public function testNotFound()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The schedule "not-exists" is not found.');

        $factory->createTransport('schedule://not-exists', [], $this->createStub(SerializerInterface::class));
    }

    public function testSupports()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->assertTrue($factory->supports('schedule://', []));
        $this->assertTrue($factory->supports('schedule://name', []));
        $this->assertFalse($factory->supports('', []));
        $this->assertFalse($factory->supports('string', []));
    }

    private function makeTransportFactoryWithStubs(): SchedulerTransportFactory
    {
        return new SchedulerTransportFactory(
            new Container([
                'default' => fn () => $this->createStub(ScheduleProviderInterface::class),
            ]),
            new MockClock(),
        );
    }
}

class Container implements ContainerInterface
{
    use ServiceLocatorTrait;
}
