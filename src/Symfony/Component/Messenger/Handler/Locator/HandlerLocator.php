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

use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class HandlerLocator implements HandlerLocatorInterface
{
    /**
     * Maps a message (its class) to a given handler.
     */
    private $messageToHandlerMapping;

    public function __construct(array $messageToHandlerMapping = array())
    {
        $this->messageToHandlerMapping = $messageToHandlerMapping;
    }

    public function resolve($message): callable
    {
        $messageKey = \get_class($message);

        if (!isset($this->messageToHandlerMapping[$messageKey])) {
            throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', $messageKey));
        }

        return $this->messageToHandlerMapping[$messageKey];
    }
}
