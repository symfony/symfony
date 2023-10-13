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

/**
 * @internal
 */
class ErrorLoggerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('debug.error_handler_configurator')) {
            return;
        }

        $definition = $container->getDefinition('debug.error_handler_configurator');
        if ($container->hasDefinition('monolog.logger.php')) {
            $definition->replaceArgument(0, new Reference('monolog.logger.php'));
        }
        if ($container->hasDefinition('monolog.logger.deprecation')) {
            $definition->replaceArgument(5, new Reference('monolog.logger.deprecation'));
        }
    }
}
