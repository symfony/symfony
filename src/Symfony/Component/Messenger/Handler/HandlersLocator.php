<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Maps a message to a list of handlers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
/** abstract */ class HandlersLocator implements HandlersLocatorInterface
{
    protected $handlers;

    /**
     * @param HandlerDescriptor[][]|callable[][] $handlers
     */
    public function __construct(array $handlers)
    {
        if (__CLASS__ === static::class) {
            trigger_deprecation('symfony/messenger', '5.4',
                'Instantiating "%s" is deprecated, use one of the provided concretions of "%s" instead.',
                static::class,
                HandlersLocatorInterface::class
            );
        }
        $this->handlers = $handlers;
    }

    public function getHandlers(Envelope $envelope): iterable
    {
        yield from $this->doGetHandlers($envelope);
    }

    /**
     * @internal
     */
    public static function listTypes(Envelope $envelope): array
    {
        $class = \get_class($envelope->getMessage());

        return [$class => $class]
            + class_parents($class)
            + class_implements($class)
            + ['*' => '*'];
    }

    protected function doGetHandlers(Envelope $envelope): iterable
    {
        $seen = [];

        foreach (self::listTypes($envelope) as $type) {
            foreach ($this->handlers[$type] ?? [] as $handlerDescriptor) {
                if (\is_callable($handlerDescriptor)) {
                    $handlerDescriptor = new HandlerDescriptor($handlerDescriptor);
                }

                if (!$this->shouldHandle($envelope, $handlerDescriptor)) {
                    continue;
                }

                $name = $handlerDescriptor->getName();
                if (\in_array($name, $seen)) {
                    continue;
                }

                $seen[] = $name;

                yield $handlerDescriptor;
            }
        }
    }

    protected function shouldHandle(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        if (null === $received = $envelope->last(ReceivedStamp::class)) {
            return true;
        }

        if (null === $expectedTransport = $handlerDescriptor->getOption('from_transport')) {
            return true;
        }

        return $received->getTransportName() === $expectedTransport;
    }

    protected function countHandlers(Envelope $envelope): int
    {
        return array_reduce(
            array_map(
                function (string $type) {
                    return $this->handlers[$type] ?? [];
                },
                self::listTypes($envelope)
            ),
            function (int $total, array $handlers) {
                return $total + \count($handlers);
            },
            0
        );
    }
}
