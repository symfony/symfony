<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\LazyProxy\Instantiator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;

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
     * @param string                                        $id               Identifier of the requested service
     * @param (callable(): object)|(callable(object): void) $realInstantiator A callback that creates or initializes the real service instance:
     *                                                                        - For direct instantiation or value-holder proxies: Called without arguments and returns the service object.
     *                                                                        - For ghost object proxies (using PHP's lazy objects): Called with the proxy as argument, initializes it in place and returns void.
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, string $id, callable $realInstantiator): object;
}
