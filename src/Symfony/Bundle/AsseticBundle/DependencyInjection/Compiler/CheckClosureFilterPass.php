<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Tags either the closure JAR or API filter for the filter manager.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class CheckClosureFilterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('assetic.filter.closure.jar') &&
            $container->getParameterBag()->resolveValue($container->getParameter('assetic.filter.closure.jar'))) {
            $container->remove('assetic.filter.closure.api');
        } elseif ($container->hasDefinition('assetic.filter.closure.api')) {
            $container->remove('assetic.filter.closure.jar');
        }
    }
}
