<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sender\Locator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Sender\Locator\ContainerSenderLocator;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class ContainerSenderLocatorTest extends TestCase
{
    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container = new Container();
        $container->set('my_amqp_sender', $sender);

        $locator = new ContainerSenderLocator($container, array(
            DummyMessage::class => 'my_amqp_sender',
        ));

        $this->assertSame($sender, $locator->getSender(DummyMessage::class));
        $this->assertNull($locator->getSender(SecondMessage::class));
    }

    public function testItReturnsTheSenderBasedOnTheTopic()
    {
        $container = new Container();
        $container->set('my_amqp_sender1', $sender1 = $this->getMockBuilder(SenderInterface::class)->getMock());
        $container->set('my_amqp_sender2', $sender2 = $this->getMockBuilder(SenderInterface::class)->getMock());

        $locator = new ContainerSenderLocator($container, array(
            DummyMessageInterface::class => 'my_amqp_sender1',
            'foo' => 'my_amqp_sender2',
        ));
        $this->assertSame($sender2, $locator->getSender('foo'));
    }

    public function testItSupportsAWildcardInsteadOfTheMessageClass()
    {
        $container = new Container();

        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container->set('my_amqp_sender', $sender);

        $apiSender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $container->set('my_api_sender', $apiSender);

        $locator = new ContainerSenderLocator($container, array(
            DummyMessage::class => 'my_amqp_sender',
            '*' => 'my_api_sender',
        ));

        $this->assertSame($sender, $locator->getSender(DummyMessage::class));
        $this->assertSame($apiSender, $locator->getSender(SecondMessage::class));
    }
}
