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
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MessageLoggerListenerTest extends TestCase
{
    public function testMessagesAreLoggedByDefault()
    {
        $listener = new MessageLoggerListener();
        $listener->onMessage($this->createEvent());

        $this->assertCount(1, $listener->getEvents()->getEvents());
    }

    public function testMessagesAreDiscardedWhileDisabled()
    {
        $listener = new MessageLoggerListener(static fn (): bool => true);
        $listener->onMessage($this->createEvent());

        $this->assertSame([], $listener->getEvents()->getEvents());
    }

    public function testDisabledStateIsCheckedOnEachMessage()
    {
        $disabled = true;
        $listener = new MessageLoggerListener(static function () use (&$disabled): bool { return $disabled; });

        $listener->onMessage($this->createEvent());
        $disabled = false;
        $listener->onMessage($this->createEvent());

        $this->assertCount(1, $listener->getEvents()->getEvents());
    }

    public function testResetDropsLoggedMessages()
    {
        $listener = new MessageLoggerListener();
        $listener->onMessage($this->createEvent());
        $listener->reset();

        $this->assertSame([], $listener->getEvents()->getEvents());
    }

    private function createEvent(): MessageEvent
    {
        $email = (new Email())->from('from@example.com')->to('to@example.com')->text('Hello!');

        return new MessageEvent($email, new Envelope(new Address('from@example.com'), [new Address('to@example.com')]), 'smtp');
    }
}
