<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sender;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterfaceWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithAttributeAndInterface;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class SendersLocatorTest extends TestCase
{
    /**
     * @requires PHP 8
     */
    public function testAttributeMapping()
    {
        $sender = $this->createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'message_attribute_sender' => $sender,
            'message_attribute_sender_2' => $sender,
            'interface_attribute_sender' => $sender,
            'message_config_sender' => $sender,
            'interface_config_sender' => $sender,
        ]);

        $locator = new SendersLocator([], $sendersLocator);
        $this->assertSame([], iterator_to_array($locator->getSenders(new Envelope(new DummyMessage('a')))));
        $this->assertSame(
            ['message_attribute_sender' => $sender, 'message_attribute_sender_2' => $sender],
            iterator_to_array($locator->getSenders(new Envelope(new DummyMessageWithAttribute('a'))))
        );
        $this->assertSame(
            ['message_attribute_sender' => $sender, 'interface_attribute_sender' => $sender],
            iterator_to_array($locator->getSenders(new Envelope(new DummyMessageWithAttributeAndInterface('a'))))
        );

        $locatorWithRouting = new SendersLocator([
            DummyMessageWithAttribute::class => ['message_config_sender'],
            DummyMessageWithAttributeAndInterface::class => ['message_config_sender'],
            DummyMessageInterfaceWithAttribute::class => ['interface_config_sender'],
        ], $sendersLocator);
        $this->assertSame(
            ['message_config_sender' => $sender],
            iterator_to_array($locatorWithRouting->getSenders(new Envelope(new DummyMessageWithAttribute('a'))))
        );
        $this->assertSame(
            ['message_config_sender' => $sender, 'interface_config_sender' => $sender],
            iterator_to_array($locatorWithRouting->getSenders(new Envelope(new DummyMessageWithAttributeAndInterface('a'))))
        );
    }

    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = $this->createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'my_sender' => $sender,
        ]);
        $locator = new SendersLocator([
            DummyMessage::class => ['my_sender'],
        ], $sendersLocator);

        $this->assertSame(['my_sender' => $sender], iterator_to_array($locator->getSenders(new Envelope(new DummyMessage('a')))));
        $this->assertSame([], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    private function createContainer(array $senders): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('has')
            ->willReturnCallback(function ($id) use ($senders) {
                return isset($senders[$id]);
            });
        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($id) use ($senders) {
                return $senders[$id];
            });

        return $container;
    }
}
