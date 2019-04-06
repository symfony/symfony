<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageHandlerFailingFirstTimes;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class RetryIntegrationTest extends TestCase
{
    public function testRetryMechanism()
    {
        $apiMessage = new DummyMessage('API');

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->method('get')
            ->willReturn([
                new Envelope($apiMessage, [
                    new SentStamp('Some\Sender', 'sender_alias'),
                ]),
            ]);

        $senderLocator = new SendersLocator([], ['*' => true]);

        $handler = new DummyMessageHandlerFailingFirstTimes();
        $throwingHandler = new DummyMessageHandlerFailingFirstTimes(1);
        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [
                'handler' => $handler,
                'throwing' => $throwingHandler,
            ],
        ]);

        $bus = new MessageBus([new SendMessageMiddleware($senderLocator), new HandleMessageMiddleware($handlerLocator)]);

        $worker = new Worker(['receiverName' => $receiver], $bus, ['receiverName' => new MultiplierRetryStrategy()]);
        $worker->run([], function () use ($worker) {
            $worker->stop();
        });

        $this->assertSame(1, $handler->getTimesCalledWithoutThrowing());
        $this->assertSame(1, $throwingHandler->getTimesCalledWithoutThrowing());
    }
}
