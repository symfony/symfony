<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler\Locator;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class HandlerLocator implements HandlerLocatorInterface
{
    /**
     * Maps a topic to a given handler.
     */
    private $topicToHandlerMapping;

    public function __construct(array $topicToHandlerMapping = array())
    {
        $this->topicToHandlerMapping = $topicToHandlerMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandler(string $topic): ?callable
    {
        return $this->topicToHandlerMapping[$topic] ?? null;
    }
}
