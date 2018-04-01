<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\DependencyInjection;

use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;

/**
 * Adds tagged routing.loader services to routing.resolver service.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class RoutingResolverPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $resolverServiceId;
    private $loaderTag;

    public function __construct(string $resolverServiceId = 'routing.resolver', string $loaderTag = 'routing.loader')
    {
        $this->resolverServiceId = $resolverServiceId;
        $this->loaderTag = $loaderTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition($this->resolverServiceId)) {
            return;
        }

        $definition = $container->getDefinition($this->resolverServiceId);

        foreach ($this->findAndSortTaggedServices($this->loaderTag, $container) as $id) {
            $definition->addMethodCall('addLoader', array(new Reference($id)));
        }
    }
}
