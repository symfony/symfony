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
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\TrustStamp;
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
            'services_resetter' => new ServicesResetter(new \ArrayIterator([new ResettableService()]), ['reset']),
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
        $stamps = [new HandledByBusStamp($this->busName), new SeenByWorkerStamp(array_keys($envelope->all()), $envelope->last(TrustStamp::class)?->isTrusted())];

        if ($envelope->last(ConnectStamp::class)) {
            $stamps[] = new ConnectionStamp(static fn () => null);
        }

        if ($envelope->last(FailingResetStamp::class)) {
            ResettableService::$failing = true;
        }

        return $stack->next()->handle($envelope->with(...$stamps), $stack);
    }
}
