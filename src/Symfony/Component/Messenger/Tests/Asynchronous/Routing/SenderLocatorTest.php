<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Asynchronous\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Asynchronous\Routing\SenderLocator;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\SenderInterface;

class SenderLocatorTest extends TestCase
{
    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container = new Container();
        $container->set('my_amqp_sender', $sender);

        $locator = new SenderLocator($container, array(
            DummyMessage::class => 'my_amqp_sender',
        ));

        $this->assertSame($sender, $locator->getSenderForMessage(new DummyMessage('Hello')));
        $this->assertNull($locator->getSenderForMessage(new SecondMessage()));
    }

    public function testItReturnsTheSenderBasedOnTheMessageParentClass()
    {
        $container = new Container();

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container->set('my_amqp_sender', $sender);

        $apiSender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container->set('my_api_sender', $apiSender);

        $locator = new SenderLocator($container, array(
            DummyMessageInterface::class => 'my_api_sender',
            DummyMessage::class => 'my_amqp_sender',
        ));

        $this->assertSame($sender, $locator->getSenderForMessage(new ChildDummyMessage('Hello')));
        $this->assertNull($locator->getSenderForMessage(new SecondMessage()));
    }

    public function testItReturnsTheSenderBasedOnTheMessageInterface()
    {
        $container = new Container();

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container->set('my_amqp_sender', $sender);

        $locator = new SenderLocator($container, array(
            DummyMessageInterface::class => 'my_amqp_sender',
        ));

        $this->assertSame($sender, $locator->getSenderForMessage(new DummyMessage('Hello')));
        $this->assertNull($locator->getSenderForMessage(new SecondMessage()));
    }

    public function testItSupportsAWildcardInsteadOfTheMessageClass()
    {
        $container = new Container();

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container->set('my_amqp_sender', $sender);

        $apiSender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container->set('my_api_sender', $apiSender);

        $locator = new SenderLocator($container, array(
            DummyMessage::class => 'my_amqp_sender',
            '*' => 'my_api_sender',
        ));

        $this->assertSame($sender, $locator->getSenderForMessage(new DummyMessage('Hello')));
        $this->assertSame($apiSender, $locator->getSenderForMessage(new SecondMessage()));
    }
}

class ChildDummyMessage extends DummyMessage
{
}
