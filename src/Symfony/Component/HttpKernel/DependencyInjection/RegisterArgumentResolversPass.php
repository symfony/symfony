<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Compiler pass to register argument resolvers.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class RegisterArgumentResolversPass implements CompilerPassInterface
{
    /**
     * @var string
     */
    private $managerService;

    /**
     * @var string
     */
    private $resolverTag;

    public function __construct($managerService = 'argument_resolver.manager', $resolverTag = 'kernel.argument_resolver')
    {
        $this->managerService = $managerService;
        $this->resolverTag = $resolverTag;
    }

    /**
    * {@inheritDoc}
    */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->managerService) && !$container->hasAlias($this->managerService)) {
            return;
        }

        $definition = $container->findDefinition($this->managerService);

        foreach ($container->findTaggedServiceIds($this->resolverTag) as $id => $resolvers) {
            $definition->addMethodCall('add', array(new Reference($id)));
        }
    }
}
