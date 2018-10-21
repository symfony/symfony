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
class HandlerLocator extends AbstractHandlerLocator
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
    protected function getHandlerByName(string $name): ?callable
    {
        return $this->topicToHandlerMapping[$name] ?? null;
    }
}
