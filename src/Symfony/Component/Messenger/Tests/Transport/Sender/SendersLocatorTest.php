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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class SendersLocatorTest extends TestCase
{
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

    public function testItReturnsTheSenderBasedOnTransportNamesStamp()
    {
        $mySender = $this->createMock(SenderInterface::class);
        $otherSender = $this->createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'my_sender' => $mySender,
            'other_sender' => $otherSender,
        ]);
        $locator = new SendersLocator([
            DummyMessage::class => ['my_sender'],
        ], $sendersLocator);

        $this->assertSame(['other_sender' => $otherSender], iterator_to_array($locator->getSenders(new Envelope(new DummyMessage('a'), [new TransportNamesStamp(['other_sender'])]))));
        $this->assertSame([], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    public function testItReturnsTheSenderBasedOnAsMessageAttribute()
    {
        $firstSender = $this->createMock(SenderInterface::class);
        $secondSender = $this->createMock(SenderInterface::class);
        $otherSender = $this->createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'first_sender' => $firstSender,
            'second_sender' => $secondSender,
            'other_sender' => $otherSender,
        ]);
        $locator = new SendersLocator([], $sendersLocator);

        $this->assertSame(['first_sender' => $firstSender, 'second_sender' => $secondSender], iterator_to_array($locator->getSenders(new Envelope(new DummyMessageWithAttribute('a')))));
        $this->assertSame([], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    public function testAsMessageAttributeIsOverridenByTransportNamesStamp()
    {
        $firstSender = $this->createMock(SenderInterface::class);
        $secondSender = $this->createMock(SenderInterface::class);
        $otherSender = $this->createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'first_sender' => $firstSender,
            'second_sender' => $secondSender,
            'other_sender' => $otherSender,
        ]);
        $locator = new SendersLocator([], $sendersLocator);

        $this->assertSame(['other_sender' => $otherSender], iterator_to_array($locator->getSenders(new Envelope(new DummyMessageWithAttribute('a'), [new TransportNamesStamp(['other_sender'])]))));
        $this->assertSame([], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    public function testAsMessageAttributeIsOverridenByUserConfiguration()
    {
        $firstSender = $this->createMock(SenderInterface::class);
        $secondSender = $this->createMock(SenderInterface::class);
        $otherSender = $this->createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'first_sender' => $firstSender,
            'second_sender' => $secondSender,
            'other_sender' => $otherSender,
        ]);
        $locator = new SendersLocator([
            DummyMessageInterface::class => ['other_sender'],
        ], $sendersLocator);

        $this->assertSame(['other_sender' => $otherSender], iterator_to_array($locator->getSenders(new Envelope(new DummyMessageWithAttribute('a')))));
        $this->assertSame([], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    public function testSendersMapWithFallback()
    {
        $firstSender = $this->createMock(SenderInterface::class);
        $secondSender = $this->createMock(SenderInterface::class);
        $thirdSender = $this->createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'first' => $firstSender,
            'second' => $secondSender,
            'third' => $thirdSender,
        ]);
        $locator = new SendersLocator([
            DummyMessage::class => ['first'],
            '*' => ['second', 'third'],
        ], $sendersLocator);

        $this->assertSame(['first' => $firstSender], iterator_to_array($locator->getSenders(new Envelope(new DummyMessage('a')))), 'Unexpected senders for configured message');
        $this->assertSame(['second' => $secondSender, 'third' => $thirdSender], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))), 'Unexpected senders for unconfigured message');
    }

    private function createContainer(array $senders): ContainerInterface
    {
        $container = new Container();

        foreach ($senders as $id => $sender) {
            $container->set($id, $sender);
        }

        return $container;
    }
}
