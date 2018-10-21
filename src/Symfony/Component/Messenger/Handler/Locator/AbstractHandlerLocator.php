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
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @internal
 */
abstract class AbstractHandlerLocator implements HandlerLocatorInterface
{
    public function getHandler(string $topic): ?callable
    {
        if ($handler = $this->getHandlerByName($topic)) {
            return $handler;
        }

        if (!class_exists($topic) && !interface_exists($topic, false)) {
            return null;
        }

        foreach (class_parents($topic) as $name) {
            if ($handler = $this->getHandlerByName($name)) {
                return $handler;
            }
        }

        foreach (class_implements($topic) as $name) {
            if ($handler = $this->getHandlerByName($name)) {
                return $handler;
            }
        }

        return null;
    }

    abstract protected function getHandlerByName(string $name): ?callable;
}
