<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RouterInterface;

class RoutingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasAlias('router')) {
            $definition = $container->findDefinition('router');
            $definition->addAutowiringType(UrlGeneratorInterface::class);
            $definition->addAutowiringType(UrlMatcherInterface::class);
            $definition->addAutowiringType(RequestMatcherInterface::class);
            $definition->addAutowiringType(RouterInterface::class);
        }
    }
}
