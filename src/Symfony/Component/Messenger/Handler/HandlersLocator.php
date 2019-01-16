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

/**
 * Maps a message to a list of handlers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.2
 */
class HandlersLocator implements HandlersLocatorInterface
{
    private $handlers;

    /**
     * @param callable[][] $handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandlers(Envelope $envelope): iterable
    {
        $seen = [];

        foreach (self::listTypes($envelope) as $type) {
            foreach ($this->handlers[$type] ?? [] as $alias => $handler) {
                if (!\in_array($handler, $seen, true)) {
                    yield $alias => $seen[] = $handler;
                }
            }
        }
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
}
