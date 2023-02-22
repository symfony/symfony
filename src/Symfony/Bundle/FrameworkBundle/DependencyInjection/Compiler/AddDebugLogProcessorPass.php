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
    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
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

        $definition = $container->getDefinition('monolog.logger_prototype');
        $definition->setConfigurator([__CLASS__, 'configureLogger']);
        $definition->addMethodCall('pushProcessor', [new Reference('debug.log_processor')]);
    }

    /**
     * @return void
     */
    public static function configureLogger(mixed $logger)
    {
        if (\is_object($logger) && method_exists($logger, 'removeDebugLogger') && \in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $logger->removeDebugLogger();
        }
    }
}
