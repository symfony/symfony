<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger;

use Psr\Container\ContainerInterface;
use Symphony\Component\Messenger\Exception\NoHandlerForMessageException;

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
        $messageClass = get_class($message);
        $handlerKey = 'handler.'.$messageClass;

        if (!$this->container->has($handlerKey)) {
            throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', $messageClass));
        }

        return $this->container->get($handlerKey);
    }
}
