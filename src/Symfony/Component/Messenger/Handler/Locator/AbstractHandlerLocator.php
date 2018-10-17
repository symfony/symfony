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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @internal
 */
abstract class AbstractHandlerLocator implements HandlerLocatorInterface
{
    public function getHandler(Envelope $envelope): callable
    {
        $class = \get_class($envelope->getMessage());

        if ($handler = $this->getHandlerByName($class)) {
            return $handler;
        }

        foreach (class_parents($class) as $name) {
            if ($handler = $this->getHandlerByName($name)) {
                return $handler;
            }
        }

        foreach (class_implements($class) as $name) {
            if ($handler = $this->getHandlerByName($name)) {
                return $handler;
            }
        }

        throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', $class));
    }

    abstract protected function getHandlerByName(string $name): ?callable;
}
