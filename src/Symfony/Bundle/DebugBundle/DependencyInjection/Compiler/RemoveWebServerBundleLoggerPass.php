<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @internal
 */
final class RemoveWebServerBundleLoggerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('web_server.command.server_log') && $container->hasDefinition('monolog.command.server_log')) {
            $container->removeDefinition('web_server.command.server_log');
        }
    }
}
