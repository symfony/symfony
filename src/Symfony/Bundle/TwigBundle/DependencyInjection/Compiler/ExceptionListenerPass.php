<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the Twig exception listener if Twig is registered as a templating engine.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class ExceptionListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('twig')) {
            return;
        }

        // to be removed in 5.0
        // register the exception listener only if it's currently used, else use the provided by FrameworkBundle
        if (null === $container->getParameter('twig.exception_listener.controller') && $container->hasDefinition('exception_listener')) {
            $container->removeDefinition('twig.exception_listener');

            return;
        }

        if ($container->hasParameter('templating.engines')) {
            $engines = $container->getParameter('templating.engines');
            if (\in_array('twig', $engines, true)) {
                $container->removeDefinition('exception_listener');

                return;
            }
        }

        $container->removeDefinition('twig.exception_listener');
    }
}
