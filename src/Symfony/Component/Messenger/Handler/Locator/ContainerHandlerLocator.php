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

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ContainerHandlerLocator implements HandlerLocatorInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function resolve($message): callable
    {
        $messageClass = \get_class($message);
        $handlerKey = 'handler.'.$messageClass;

        if (!$this->container->has($handlerKey)) {
            throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', $messageClass));
        }

        return $this->container->get($handlerKey);
    }
}
