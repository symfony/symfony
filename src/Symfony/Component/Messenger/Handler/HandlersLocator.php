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
    public function getHandlers(string $name): iterable
    {
        $seen = array();

        foreach (self::listTypes($name) as $type) {
            foreach ($this->handlers[$type] ?? array() as $handler) {
                if (!\in_array($handler, $seen, true)) {
                    yield $seen[] = $handler;
                }
            }
        }
    }

    /**
     * @internal
     */
    public static function listTypes(string $class): array
    {
        if (!class_exists($class, false)) {
            return array($class => $class, '*' => '*');
        }

        return array($class => $class)
            + class_parents($class)
            + class_implements($class)
            + array('*' => '*');
    }
}
