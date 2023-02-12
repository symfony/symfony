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
use Symfony\Component\HttpKernel\Log\Logger;

final class EnableLoggerDebugModePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('profiler') || !$container->hasDefinition('logger')) {
            return;
        }

        $loggerDefinition = $container->getDefinition('logger');

        if (Logger::class === $loggerDefinition->getClass()) {
            $loggerDefinition->setConfigurator([__CLASS__, 'configureLogger']);
        }
    }

    public static function configureLogger(Logger $logger): void
    {
        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && method_exists($logger, 'enableDebug')) {
            $logger->enableDebug();
        }
    }
}
