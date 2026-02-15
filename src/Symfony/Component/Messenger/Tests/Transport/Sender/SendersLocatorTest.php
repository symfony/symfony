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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithInterfaceWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithParentWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class SendersLocatorTest extends TestCase
{
    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = $this->createStub(SenderInterface::class);
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
        $mySender = $this->createStub(SenderInterface::class);
        $otherSender = $this->createStub(SenderInterface::class);
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

    #[TestWith([DummyMessageWithAttribute::class, ['first_sender', 'second_sender']])]
    #[TestWith([DummyMessageWithParentWithAttribute::class, ['third_sender', 'first_sender', 'second_sender']])]
    #[TestWith([DummyMessageWithInterfaceWithAttribute::class, ['first_sender', 'third_sender', 'second_sender']])]
    public function testItReturnsTheSenderBasedOnAsMessageAttribute(string $messageClass, array $expectedSenders)
    {
        $firstSender = $this->createStub(SenderInterface::class);
        $secondSender = $this->createStub(SenderInterface::class);
        $thirdSender = $this->createStub(SenderInterface::class);
        $otherSender = $this->createStub(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'first_sender' => $firstSender,
            'second_sender' => $secondSender,
            'third_sender' => $thirdSender,
            'other_sender' => $otherSender,
        ]);
        $locator = new SendersLocator([], $sendersLocator);

        $this->assertSame($expectedSenders, array_keys(iterator_to_array($locator->getSenders(new Envelope(new $messageClass('a'))))));
        $this->assertSame([], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    public function testAsMessageAttributeIsOverridenByTransportNamesStamp()
    {
        $firstSender = $this->createStub(SenderInterface::class);
        $secondSender = $this->createStub(SenderInterface::class);
        $otherSender = $this->createStub(SenderInterface::class);
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
        $firstSender = $this->createStub(SenderInterface::class);
        $secondSender = $this->createStub(SenderInterface::class);
        $otherSender = $this->createStub(SenderInterface::class);
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
        $firstSender = $this->createStub(SenderInterface::class);
        $secondSender = $this->createStub(SenderInterface::class);
        $thirdSender = $this->createStub(SenderInterface::class);
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
