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
use Symfony\Component\HttpKernel\Log\Logger;

class AddDebugLogProcessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('profiler')) {
            return;
        }

        if ($container->hasDefinition('monolog.logger_prototype') && $container->hasDefinition('debug.log_processor')) {
            $container->getDefinition('monolog.logger_prototype')
                ->setConfigurator([__CLASS__, 'configureMonologLogger'])
                ->addMethodCall('pushProcessor', [new Reference('debug.log_processor')])
            ;

            return;
        }

        if (!$container->hasDefinition('logger')) {
            return;
        }

        $loggerDefinition = $container->getDefinition('logger');

        if (Logger::class === $loggerDefinition->getClass()) {
            $loggerDefinition->setConfigurator([__CLASS__, 'configureHttpKernelLogger']);
        }
    }

    public static function configureMonologLogger(mixed $logger)
    {
        if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && \is_object($logger) && method_exists($logger, 'removeDebugLogger')) {
            $logger->removeDebugLogger();
        }
    }

    public static function configureHttpKernelLogger(Logger $logger)
    {
        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && method_exists($logger, 'enableDebug')) {
            $logger->enableDebug();
        }
    }
}
