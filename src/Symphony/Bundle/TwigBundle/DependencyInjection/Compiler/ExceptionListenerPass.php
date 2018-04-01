<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Registers the Twig exception listener if Twig is registered as a templating engine.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ExceptionListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('twig')) {
            return;
        }

        // register the exception controller only if Twig is enabled and required dependencies do exist
        if (!class_exists('Symphony\Component\Debug\Exception\FlattenException') || !interface_exists('Symphony\Component\EventDispatcher\EventSubscriberInterface')) {
            $container->removeDefinition('twig.exception_listener');
        } elseif ($container->hasParameter('templating.engines')) {
            $engines = $container->getParameter('templating.engines');
            if (!in_array('twig', $engines)) {
                $container->removeDefinition('twig.exception_listener');
            }
        }
    }
}
