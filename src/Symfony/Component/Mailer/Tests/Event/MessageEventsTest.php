<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\Transports;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Mime\Email;

class MessageEventsTest extends TestCase
{
    public function testMessageSentThroughASynchronousBusIsReportedOnce()
    {
        $dispatcher = new EventDispatcher();
        $logger = new MessageLoggerListener();
        $transports = new Transports(['main' => new NullTransport($dispatcher)]);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([SendEmailMessage::class => [new MessageHandler($transports)]]))]);

        $events = $this->sendTwoEmails($dispatcher, $logger, $transports, $bus);

        $this->assertCount(4, $events->getEvents());

        $messages = $events->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('First', $messages[0]->getSubject());
        $this->assertSame('Second', $messages[1]->getSubject());
        $this->assertSame('sent', $messages[0]->getHeaders()->get('X-Status')->getBody());
        $this->assertSame('sent', $messages[1]->getHeaders()->get('X-Status')->getBody());
    }

    public function testMessageLeftInTheQueueIsStillReported()
    {
        $dispatcher = new EventDispatcher();
        $logger = new MessageLoggerListener();
        $transports = new Transports(['main' => new NullTransport($dispatcher)]);

        $events = $this->sendTwoEmails($dispatcher, $logger, $transports, new MessageBus());

        $this->assertCount(2, $events->getEvents());

        $messages = $events->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('First', $messages[0]->getSubject());
        $this->assertSame('Second', $messages[1]->getSubject());
        $this->assertSame('queued', $messages[0]->getHeaders()->get('X-Status')->getBody());
        $this->assertSame('queued', $messages[1]->getHeaders()->get('X-Status')->getBody());
    }

    public function testQueuedMessageIsKeptWhenAnEmailWasSentBefore()
    {
        $dispatcher = new EventDispatcher();
        $logger = new MessageLoggerListener();
        $dispatcher->addSubscriber($logger);
        $transports = new Transports(['main' => new NullTransport($dispatcher)]);

        $mailer = new Mailer($transports, null, $dispatcher);
        $mailer->send((new Email())->from('sender@example.com')->to('recipient@example.com')->subject('Sent')->text('Hello'));

        $mailer = new Mailer($transports, new MessageBus(), $dispatcher);
        $mailer->send((new Email())->from('sender@example.com')->to('recipient@example.com')->subject('Queued')->text('Hello'));

        $messages = $logger->getEvents()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('Sent', $messages[0]->getSubject());
        $this->assertSame('Queued', $messages[1]->getSubject());
    }

    private function sendTwoEmails(EventDispatcher $dispatcher, MessageLoggerListener $logger, Transports $transports, MessageBusInterface $bus): MessageEvents
    {
        $dispatcher->addSubscriber($logger);
        $dispatcher->addListener(MessageEvent::class, static function (MessageEvent $event) {
            $event->getMessage()->getHeaders()->addTextHeader('X-Status', $event->isQueued() ? 'queued' : 'sent');
        });

        $mailer = new Mailer($transports, $bus, $dispatcher);
        $mailer->send((new Email())->from('sender@example.com')->to('recipient@example.com')->subject('First')->text('Hello'));
        $mailer->send((new Email())->from('sender@example.com')->to('recipient@example.com')->subject('Second')->text('Hello'));

        return $logger->getEvents();
    }
}
