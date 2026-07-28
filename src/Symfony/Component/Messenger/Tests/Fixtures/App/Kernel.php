<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures\App;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Contracts\Service\ContainerProviderInterface;

final class App implements ContainerProviderInterface
{
    private readonly ContainerInterface $container;

    public function __construct()
    {
        $defaultBus = new MessageBus([new StampingMiddleware('default.bus'), new BatchHandlingMiddleware()]);
        $otherBus = new MessageBus([new StampingMiddleware('other.bus'), new BatchHandlingMiddleware()]);

        $this->container = new TestContainer([
            'messenger.routable_message_bus' => new RoutableMessageBus(new BusLocator($defaultBus, $otherBus), $defaultBus),
        ]);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}

final class TestContainer implements ContainerInterface
{
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): mixed
    {
        return $this->services[$id] ?? throw new \InvalidArgumentException(\sprintf('Unknown service "%s".', $id));
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

final class BusLocator implements ContainerInterface
{
    public function __construct(
        private readonly MessageBusInterface $defaultBus,
        private readonly MessageBusInterface $otherBus,
    ) {
    }

    public function get(string $id): mixed
    {
        return match ($id) {
            'message.bus' => $this->defaultBus,
            'other.bus' => $this->otherBus,
            default => throw new \InvalidArgumentException(\sprintf('Unknown bus "%s".', $id)),
        };
    }

    public function has(string $id): bool
    {
        return \in_array($id, ['message.bus', 'other.bus'], true);
    }
}

final class StampingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $busName)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope->with(new HandledByBusStamp($this->busName)), $stack);
    }
}

final class BatchHandlingMiddleware implements MiddlewareInterface
{
    private readonly HandleMessageMiddleware $middleware;

    public function __construct()
    {
        $this->middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor(new BatchSizeReportingHandler())],
        ]));
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $this->middleware->handle($envelope, $stack);
    }
}

final class BatchSizeReportingHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __invoke(DummyMessage $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    private function getBatchSize(): int
    {
        return 2;
    }

    private function process(array $jobs): void
    {
        foreach ($jobs as [$job]) {
            if ('fail' === $job->getMessage()) {
                throw new \RuntimeException('Parallel handler failed.');
            }
        }

        $batchSize = \count($jobs);

        foreach ($jobs as [, $ack]) {
            $ack->ack($batchSize);
        }
    }
}
