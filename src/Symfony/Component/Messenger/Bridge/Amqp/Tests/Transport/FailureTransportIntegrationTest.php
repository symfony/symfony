<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Amqp\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

/**
 * @requires extension amqp
 *
 * @group integration
 */
class FailureTransportIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!getenv('MESSENGER_AMQP_DSN')) {
            $this->markTestSkipped('The "MESSENGER_AMQP_DSN" environment variable is required.');
        }
    }

    public function testItDoesNotLoseMessagesFromTheFailedTransport()
    {
        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'));
        $connection->setup();
        $connection->purgeQueues();

        $failureConnection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'),
            ['exchange' => [
                'name' => 'failed',
                'type' => 'fanout',
            ], 'queues' => ['failed' => []]]
        );
        $failureConnection->setup();
        $failureConnection->purgeQueues();

        $originalTransport = new AmqpTransport($connection);
        $failureTransport = new AmqpTransport($failureConnection);

        $retryStrategy = new MultiplierRetryStrategy(1, 100, 2);
        $retryStrategyLocator = $this->createStub(ContainerInterface::class);
        $retryStrategyLocator->method('has')->willReturn(true);
        $retryStrategyLocator->method('get')->willReturn($retryStrategy);

        $sendersLocatorFailureTransport = new ServiceLocator([
            'original' => static fn () => $failureTransport,
        ]);

        $transports = [
            'original' => $originalTransport,
            'failed' => $failureTransport,
        ];

        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturnCallback(static fn ($transportName) => $transports[$transportName]);
        $senderLocator = new SendersLocator(
            [DummyMessage::class => ['original']],
            $locator
        );

        $timesHandled = 0;

        $handler = static function () use (&$timesHandled) {
            ++$timesHandled;
            throw new \Exception('Handler failed');
        };

        $handlerLocator = new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler, ['from_transport' => 'original'])],
        ]);

        $bus = new MessageBus([
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware($senderLocator),
            new HandleMessageMiddleware($handlerLocator),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($locator, $retryStrategyLocator));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
            $sendersLocatorFailureTransport, null, $retryStrategyLocator
        ));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addSubscriber(new StopWorkerOnTimeLimitListener(2));

        $originalTransport->send(Envelope::wrap(new DummyMessage('dummy')));

        $runWorker = static function (string $transportName) use ($bus, $dispatcher, $transports): void {
            (new Worker(
                [$transportName => $transports[$transportName]],
                $bus,
                $dispatcher,
            ))->run();
        };

        $runWorker('original');
        $runWorker('original');
        $runWorker('failed');
        $runWorker('failed');

        $this->assertSame(4, $timesHandled);
        $failedMessage = $this->waitForFailedMessage($failureTransport, 2);
        // 100 delay * 2 multiplier ^ 3 retries = 800 expected delay
        $this->assertSame(800, $failedMessage->last(DelayStamp::class)->getDelay());
        $this->assertSame(0, $failedMessage->last(RedeliveryStamp::class)->getRetryCount());
        $this->assertCount(4, $failedMessage->all(RedeliveryStamp::class));
        $this->assertCount(2, $failedMessage->all(SentToFailureTransportStamp::class));
        foreach ($failedMessage->all(SentToFailureTransportStamp::class) as $stamp) {
            $this->assertSame('original', $stamp->getOriginalReceiverName());
        }
    }

    private function waitForFailedMessage(AmqpTransport $failureTransport, int $timeOutInS): Envelope
    {
        $start = microtime(true);
        while (microtime(true) - $start < $timeOutInS) {
            $envelopes = iterator_to_array($failureTransport->get());
            if (\count($envelopes) > 0) {
                foreach ($envelopes as $envelope) {
                    $failureTransport->reject($envelope);
                }

                return $envelopes[0];
            }
            usleep(100 * 1000);
        }
        throw new \RuntimeException('Message was not received from failure transport within expected timeframe.');
    }
}
