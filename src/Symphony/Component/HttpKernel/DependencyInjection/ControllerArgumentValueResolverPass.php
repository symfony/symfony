<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\DependencyInjection;

use Symphony\Component\DependencyInjection\Argument\IteratorArgument;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gathers and configures the argument value resolvers.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class ControllerArgumentValueResolverPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $argumentResolverService;
    private $argumentValueResolverTag;

    public function __construct(string $argumentResolverService = 'argument_resolver', string $argumentValueResolverTag = 'controller.argument_value_resolver')
    {
        $this->argumentResolverService = $argumentResolverService;
        $this->argumentValueResolverTag = $argumentValueResolverTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->argumentResolverService)) {
            return;
        }

        $container
            ->getDefinition($this->argumentResolverService)
            ->replaceArgument(1, new IteratorArgument($this->findAndSortTaggedServices($this->argumentValueResolverTag, $container)))
        ;
    }
}
