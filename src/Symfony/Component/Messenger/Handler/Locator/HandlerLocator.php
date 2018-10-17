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
     * Maps a message (its class) to a given handler.
     */
    private $messageToHandlerMapping;

    public function __construct(array $messageToHandlerMapping = array())
    {
        $this->messageToHandlerMapping = $messageToHandlerMapping;
    }

    /**
     * {@inheritdoc}
     */
    protected function getHandlerByName(string $name): ?callable
    {
        return $this->messageToHandlerMapping[$name] ?? null;
    }
}
