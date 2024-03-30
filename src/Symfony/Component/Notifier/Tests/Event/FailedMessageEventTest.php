<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Event\FailedMessageEvent;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Notifier\Transport\NullTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FailedMessageEventTest extends TestCase
{
    /**
     * @dataProvider messagesProvider
     */
    public function testConstruct(MessageInterface $message, \Throwable $error, FailedMessageEvent $event)
    {
        $this->assertEquals($event, new FailedMessageEvent($message, $error));
    }

    /**
     * @dataProvider messagesProvider
     */
    public function testGetMessage(MessageInterface $message, \Throwable $error, FailedMessageEvent $event)
    {
        $this->assertSame($message, $event->getMessage());
    }

    /**
     * @dataProvider messagesProvider
     */
    public function testGetError(MessageInterface $message, \Throwable $error, FailedMessageEvent $event)
    {
        $this->assertSame($error, $event->getError());
    }

    public function testFailedMessageEventIsDisptachIfError()
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $clientMock = $this->createMock(HttpClientInterface::class);

        $transport = new class($clientMock, $eventDispatcherMock) extends AbstractTransport {
            public NullTransportException $exception;

            public function __construct($client, ?EventDispatcherInterface $dispatcher = null)
            {
                $this->exception = new NullTransportException();
                parent::__construct($client, $dispatcher);
            }

            public function doSend(MessageInterface $message): SentMessage
            {
                throw $this->exception;
            }

            public function supports(MessageInterface $message): bool
            {
                return true;
            }

            public function __toString(): string
            {
                return '';
            }
        };

        $message = new DummyMessage();

        $series = [
            new MessageEvent($message),
            new FailedMessageEvent($message, $transport->exception),
        ];

        $eventDispatcherMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$series) {
                $this->assertEquals(array_shift($series), $event);

                return $event;
            });
        try {
            $transport->send($message);
        } catch (NullTransportException $exception) {
            // catch Exception that is voluntary thrown in NullTransport::send
        }
    }

    public static function messagesProvider(): iterable
    {
        yield [$message = new ChatMessage('subject'), $error = new \RuntimeException(), new FailedMessageEvent($message, $error)];
        yield [$message = new SmsMessage('+3312345678', 'subject'), $error = new \Exception(), new FailedMessageEvent($message, $error)];
    }
}

class NullTransportException extends \Exception
{
}
