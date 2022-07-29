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
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Locator\ScheduleConfigLocatorInterface;
use Symfony\Component\Scheduler\Messenger\ScheduleTransport;
use Symfony\Component\Scheduler\Messenger\ScheduleTransportFactory;
use Symfony\Component\Scheduler\Schedule\Schedule;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;
use Symfony\Component\Scheduler\State\StateFactoryInterface;
use Symfony\Component\Scheduler\State\StateInterface;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class ScheduleTransportFactoryTest extends TestCase
{
    public function testCreateTransport()
    {
        $trigger = $this->createMock(TriggerInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $clock = $this->createMock(ClockInterface::class);
        $container = new class() extends \ArrayObject implements ScheduleConfigLocatorInterface {
            public function get(string $id): ScheduleConfig
            {
                return $this->offsetGet($id);
            }

            public function has(string $id): bool
            {
                return $this->offsetExists($id);
            }
        };

        $stateFactory = $this->createMock(StateFactoryInterface::class);
        $stateFactory
            ->expects($this->exactly(2))
            ->method('create')
            ->withConsecutive(
                ['default', ['cache' => null, 'lock' => null]],
                ['custom', ['cache' => 'app', 'lock' => null]]
            )
            ->willReturn($state = $this->createMock(StateInterface::class));

        $container['default'] = new ScheduleConfig([[$trigger, (object) ['id' => 'default']]]);
        $container['custom'] = new ScheduleConfig([[$trigger, (object) ['id' => 'custom']]]);
        $default = new ScheduleTransport(new Schedule($clock, $state, $container['default']));
        $custom = new ScheduleTransport(new Schedule($clock, $state, $container['custom']));

        $factory = new ScheduleTransportFactory($clock, $container, $stateFactory);

        $this->assertEquals($default, $factory->createTransport('schedule://default', [], $serializer));
        $this->assertEquals($custom, $factory->createTransport('schedule://custom', ['cache' => 'app'], $serializer));
    }

    public function testInvalidDsn()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Schedule DSN "schedule://#wrong" is invalid.');

        $factory->createTransport('schedule://#wrong', [], $this->createMock(SerializerInterface::class));
    }

    public function testNoName()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Schedule DSN must contains a name, e.g. "schedule://default".');

        $factory->createTransport('schedule://', [], $this->createMock(SerializerInterface::class));
    }

    public function testInvalidOption()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option(s) "invalid" passed to the Schedule Messenger transport.');

        $factory->createTransport('schedule://name', ['invalid' => true], $this->createMock(SerializerInterface::class));
    }

    public function testNotFound()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The schedule "not-exists" is not found.');

        $factory->createTransport('schedule://not-exists', [], $this->createMock(SerializerInterface::class));
    }

    public function testSupports()
    {
        $factory = $this->makeTransportFactoryWithStubs();

        $this->assertTrue($factory->supports('schedule://', []));
        $this->assertTrue($factory->supports('schedule://name', []));
        $this->assertFalse($factory->supports('', []));
        $this->assertFalse($factory->supports('string', []));
    }

    private function makeTransportFactoryWithStubs(): ScheduleTransportFactory
    {
        return new ScheduleTransportFactory(
            $this->createMock(ClockInterface::class),
            $this->createMock(ScheduleConfigLocatorInterface::class),
            $this->createMock(StateFactoryInterface::class)
        );
    }
}
