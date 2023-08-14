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

        $container->getDefinition('monolog.logger_prototype')
            ->setConfigurator([new Reference('debug.debug_logger_configurator'), 'pushDebugLogger']);
    }

    /**
     * @deprecated since Symfony 6.4, use HttpKernel's DebugLoggerConfigurator instead
     *
     * @return void
     */
    public static function configureLogger(mixed $logger)
    {
        trigger_deprecation('symfony/framework-bundle', '6.4', 'The "%s()" method is deprecated, use HttpKernel\'s DebugLoggerConfigurator instead.', __METHOD__);

        if (\is_object($logger) && method_exists($logger, 'removeDebugLogger') && \in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $logger->removeDebugLogger();
        }
    }
}
