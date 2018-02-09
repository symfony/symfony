<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class RemoveInvalidDefinitionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('twig')) {
            $container->removeDefinition('web_profiler.controller.exception');
            $container->removeDefinition('web_profiler.controller.profiler');
            $container->removeDefinition('web_profiler.controller.router');
            $container->removeDefinition('web_profiler.debug_toolbar');
        }
    }
}
