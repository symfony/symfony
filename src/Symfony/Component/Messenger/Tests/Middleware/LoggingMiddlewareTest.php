<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\LoggingMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

class LoggingMiddlewareTest extends MiddlewareTestCase
{
    #[DataProvider('provideDurationAndMemoryUsageByStack')]
    public function testHandle(
        callable $nextMiddlewares,
        int $durationInMilliseconds,
        int $memoryUsageDelta,
    ) {
        $clock = new MockClock();
        $memoryResolver = $this->createMemoryResolver($memoryUsageDelta);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('"{class}" message successfully handled.', [
                'class' => DummyMessage::class,
                'duration_ms' => $durationInMilliseconds,
                'memory_usage' => $memoryUsageDelta,
            ])
        ;

        $loggingMiddleware = new LoggingMiddleware($logger, $clock, $memoryResolver);

        $loggingMiddleware->handle(new Envelope(
            new DummyMessage('Hello')),
            new StackMiddleware(new \ArrayIterator([null, ...$nextMiddlewares($clock)]))
        );
    }

    public static function provideDurationAndMemoryUsageByStack(): iterable
    {
        $nextMiddleware = static fn (ClockInterface $clock, int $durationInSeconds): MiddlewareInterface => new class($clock, $durationInSeconds) implements MiddlewareInterface {
            public function __construct(private ClockInterface $clock, private int $durationInSeconds)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->clock->sleep($this->durationInSeconds);

                return $stack->next()->handle($envelope, $stack);
            }
        };

        yield 'Stack is empty' => [
            static fn (): array => [],
            0,
            0,
        ];

        yield 'One middleware in stack' => [
            static fn (ClockInterface $clock): array => [
                $nextMiddleware($clock, 1),
            ],
            1000,
            1024,
        ];

        yield 'More than one middleware in stack' => [
            static fn (ClockInterface $clock): array => [
                $nextMiddleware($clock, 1),
                $nextMiddleware($clock, 2),
            ],
            3000,
            2048,
        ];
    }

    public function testHandleWhenTheMessageIsOnlySentToATransport()
    {
        $clock = new MockClock();
        $memoryResolver = $this->createMemoryResolver(0);

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->willReturnArgument(0);

        $sendersLocator = new class($sender) implements SendersLocatorInterface {
            public function __construct(private SenderInterface $sender)
            {
            }

            public function getSenders(Envelope $envelope): iterable
            {
                yield 'async' => $this->sender;
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('"{class}" message sent to transport.', [
                'class' => DummyMessage::class,
                'duration_ms' => 0,
                'memory_usage' => 0,
            ])
        ;

        $loggingMiddleware = new LoggingMiddleware($logger, $clock, $memoryResolver);

        $loggingMiddleware->handle(
            new Envelope(new DummyMessage('Hello')),
            new StackMiddleware(new \ArrayIterator([null, new SendMessageMiddleware($sendersLocator)]))
        );
    }

    public function testHandleWithException()
    {
        $clock = new MockClock();
        $memoryResolver = $this->createMemoryResolver(0);

        $exception = new \RuntimeException('Thrown from next middleware.');

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->expects($this->once())
            ->method('handle')
            ->willThrowException($exception)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Unable to handle "{class}" message.', [
                'class' => DummyMessage::class,
                'duration_ms' => 0,
                'memory_usage' => 0,
                'exception' => $exception,
            ])
        ;

        $loggingMiddleware = new LoggingMiddleware($logger, $clock, $memoryResolver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');

        $loggingMiddleware->handle(new Envelope(
            new DummyMessage('Hello')),
            new StackMiddleware(new \ArrayIterator([null, $nextMiddleware]))
        );
    }

    /**
     * @return callable(): int
     */
    private function createMemoryResolver(int $memoryUsageDelta): callable
    {
        $values = [0, $memoryUsageDelta];
        $index = 0;

        return static function () use ($values, &$index): int {
            return $values[$index++];
        };
    }
}
