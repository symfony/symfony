<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Channel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Channel\EmailChannel;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

class EmailChannelTest extends TestCase
{
    public function testQueuedMessageEventIsDispatchedBeforeSendingToTheBus()
    {
        $dispatcher = new EventDispatcher();
        $events = [];
        $dispatcher->addListener(MessageEvent::class, static function (MessageEvent $event) use (&$events) {
            $events[] = $event;
            $event->addStamp(new DelayStamp(1000));
        });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (SendEmailMessage $message) use (&$events) {
                    $email = $events[0]->getMessage();

                    return $message->getMessage() instanceof Email
                        && $message->getMessage() !== $email
                        && $email->getTo() == $message->getMessage()->getTo();
                }),
                [new DelayStamp(1000)],
            )
            ->willReturn(new Envelope(new \stdClass()));

        $channel = new EmailChannel(null, $bus, 'from@example.com', null, $dispatcher);
        $channel->notify(new Notification('Hello'), new Recipient('to@example.com'));

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->isQueued());
    }

    public function testRejectedMessageIsNotSentToTheBus()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MessageEvent::class, static fn (MessageEvent $event) => $event->reject());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $channel = new EmailChannel(null, $bus, 'from@example.com', null, $dispatcher);
        $channel->notify(new Notification('Hello'), new Recipient('to@example.com'));
    }
}
