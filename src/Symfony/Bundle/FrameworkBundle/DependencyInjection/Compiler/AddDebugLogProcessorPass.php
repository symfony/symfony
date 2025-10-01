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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AddDebugLogProcessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('profiler')) {
            return;
        }
        if (!$container->hasDefinition('monolog.logger_prototype')) {
            return;
        }
        if (!$container->hasDefinition('debug.log_processor')) {
            return;
        }

        $container->getDefinition('monolog.logger_prototype')
            ->setConfigurator([new Reference('debug.debug_logger_configurator'), 'pushDebugLogger']);
    }
}
