<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Monolog\Logger;

/**
 * Adds the DebugHandler when the profiler is enabled.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class DebugHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('monolog.logger_prototype') || !$container->hasDefinition('profiler')) {
            return;
        }

        $debugHandler = new Definition('%monolog.handler.debug.class%', array(Logger::DEBUG, true));
        $container->setDefinition('monolog.handler.debug', $debugHandler);
        $container->getDefinition('monolog.logger_prototype')->addMethodCall('pushHandler', array (new Reference('monolog.handler.debug')));
    }
}
