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

/**
 * Wires the notifier email channel to the mailer, or drops it when the mailer is missing.
 *
 * @internal
 */
class RemoveMissingMailerDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('notifier.channel.email')) {
            return;
        }

        if (!$container->hasDefinition('mailer.envelope_listener')) {
            $container->removeDefinition('notifier.channel.email');

            return;
        }

        $sender = $container->getDefinition('mailer.envelope_listener')->getArgument(0);
        $container->getDefinition('notifier.channel.email')->setArgument(2, $sender);
    }
}
