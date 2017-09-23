<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the default logger if necessary.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class LoggerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $alias = $container->setAlias(LoggerInterface::class, 'logger');
        $alias->setPublic(false);

        if ($container->has('logger')) {
            return;
        }

        $loggerDefinition = $container->register('logger', Logger::class);
        $loggerDefinition->setPublic(false);
        if ($container->getParameter('kernel.debug')) {
            $loggerDefinition->addArgument(LogLevel::DEBUG);
        }
    }
}
