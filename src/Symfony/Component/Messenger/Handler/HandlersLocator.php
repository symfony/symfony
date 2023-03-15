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
class HandlersLocator implements HandlersLocatorInterface
{
    private array $handlers;

    /**
     * @param HandlerDescriptor[][]|callable[][] $handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    public function getHandlers(Envelope $envelope): iterable
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

    /**
     * @internal
     */
    public static function listTypes(Envelope $envelope): array
    {
        $class = $envelope->getMessage()::class;

        return [$class => $class]
            + class_parents($class)
            + class_implements($class)
            + self::listWildcards($class)
            + ['*' => '*'];
    }

    private static function listWildcards(string $type): array
    {
        $type .= '\*';
        $wildcards = [];
        while ($i = strrpos($type, '\\', -3)) {
            $type = substr_replace($type, '\*', $i);
            $wildcards[$type] = $type;
        }

        return $wildcards;
    }

    private function shouldHandle(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        if (null === $received = $envelope->last(ReceivedStamp::class)) {
            return true;
        }

        if (null === $expectedTransport = $handlerDescriptor->getOption('from_transport')) {
            return true;
        }

        return $received->getTransportName() === $expectedTransport;
    }
}
