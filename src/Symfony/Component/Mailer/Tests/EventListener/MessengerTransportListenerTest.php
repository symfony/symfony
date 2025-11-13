<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\MessengerTransportListener;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;

class MessengerTransportListenerTest extends TestCase
{
    public function testNoMessengerTransportStampsByDefault()
    {
        $l = new MessengerTransportListener();
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')]);
        $message = new Message(new Headers());
        $event = new MessageEvent($message, $envelope, 'smtp', true);
        $l->onMessage($event);
        $this->assertSame([], $event->getStamps());
    }

    public function testMessengerTransportStampViaHeader()
    {
        $l = new MessengerTransportListener();
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')]);
        $headers = (new Headers())->addTextHeader('X-Bus-Transport', 'async');
        $message = new Message($headers);
        $event = new MessageEvent($message, $envelope, 'smtp', true);
        $l->onMessage($event);
        $this->assertCount(1, $event->getStamps());
        $this->assertInstanceOf(TransportNamesStamp::class, $stamp = $event->getStamps()[0]);
        $this->assertSame(['async'], $stamp->getTransportNames());
        $this->assertFalse($message->getHeaders()->has('X-Bus-Transport'));
    }

    public function testMessengerTransportStampsViaHeader()
    {
        $l = new MessengerTransportListener();
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')]);
        $name = 'söme_very_long_and_weïrd transport name-for-messenger!';
        $headers = (new Headers())->addTextHeader('X-Bus-Transport', ' async , async1,'.$name);
        $message = new Message($headers);
        $event = new MessageEvent($message, $envelope, 'smtp', true);
        $l->onMessage($event);
        $this->assertCount(1, $event->getStamps());
        $this->assertInstanceOf(TransportNamesStamp::class, $stamp = $event->getStamps()[0]);
        $this->assertSame(['async', 'async1', $name], $stamp->getTransportNames());
        $this->assertFalse($message->getHeaders()->has('X-Bus-Transport'));
    }
}
