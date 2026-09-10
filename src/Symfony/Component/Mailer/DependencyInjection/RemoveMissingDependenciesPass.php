<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Drops the services that the container cannot wire, because what they depend on or serve is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mailer.message_logger_listener') || $container->has('test.client')) {
            // the test assertions read the listener directly, so it must keep collecting unconditionally
            return;
        }

        // the listener keeps every one of them for the lifetime of the process, so let it skip
        // the messages nobody will collect
        $container->getDefinition('mailer.message_logger_listener')
            ->setArgument(0, new Reference('profiler.is_disabled_state_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }
}
