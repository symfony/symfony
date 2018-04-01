<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\LazyProxy\Instantiator;

use Symphony\Component\DependencyInjection\ContainerInterface;
use Symphony\Component\DependencyInjection\Definition;

/**
 * Lazy proxy instantiator, capable of instantiating a proxy given a container, the
 * service definitions and a callback that produces the real service instance.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
interface InstantiatorInterface
{
    /**
     * Instantiates a proxy object.
     *
     * @param ContainerInterface $container        The container from which the service is being requested
     * @param Definition         $definition       The definition of the requested service
     * @param string             $id               Identifier of the requested service
     * @param callable           $realInstantiator Zero-argument callback that is capable of producing the real service instance
     *
     * @return object
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, $id, $realInstantiator);
}
