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

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the Twig exception listener if Twig is registered as a templating engine.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('twig')) {
            return;
        }

        // register the exception controller only if Twig is enabled and required dependencies do exist
        if (!$container->classExists(FlattenException::class) || !$container->classExists(EventSubscriberInterface::class)) {
            $container->removeDefinition('twig.exception_listener');
        } elseif ($container->hasParameter('templating.engines')) {
            $engines = $container->getParameter('templating.engines');
            if (!in_array('twig', $engines)) {
                $container->removeDefinition('twig.exception_listener');
            }
        }
    }
}
