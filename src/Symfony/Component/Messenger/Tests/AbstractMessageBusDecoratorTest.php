<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\AbstractMessageBusDecorator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class AbstractMessageBusDecoratorTest extends TestCase
{
    public function testItCanBeExtendedAndProxiesTheMessagesToTheBus()
    {
        $message = new DummyMessage('Foo');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($message)->willReturn('bar');

        $this->assertSame('bar', (new CommandBus($bus))->dispatch($message));
    }
}

class CommandBus extends AbstractMessageBusDecorator
{
}
