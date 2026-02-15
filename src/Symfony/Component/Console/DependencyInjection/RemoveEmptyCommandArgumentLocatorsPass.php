<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes empty service-locators registered for ServiceValueResolver for commands.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class RemoveEmptyCommandArgumentLocatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.argument_resolver.service')) {
            return;
        }

        $serviceResolverDef = $container->getDefinition('console.argument_resolver.service');
        $commandLocatorRef = $serviceResolverDef->getArgument(0);

        if (!$commandLocatorRef) {
            return;
        }

        $commandLocator = $container->getDefinition((string) $commandLocatorRef);

        if ($commandLocator->getFactory()) {
            $commandLocator = $container->getDefinition($commandLocator->getFactory()[0]);
        }

        $commands = $commandLocator->getArgument(0);

        foreach ($commands as $commandName => $argumentRef) {
            $argumentLocator = $container->getDefinition((string) $argumentRef->getValues()[0]);

            if ($argumentLocator->getFactory()) {
                $argumentLocator = $container->getDefinition($argumentLocator->getFactory()[0]);
            }

            if (!$argumentLocator->getArgument(0)) {
                $reason = \sprintf('Removing service-argument resolver for command "%s": no corresponding services exist for the referenced types.', $commandName);
                unset($commands[$commandName]);
                $container->log($this, $reason);
            }
        }

        $commandLocator->replaceArgument(0, $commands);
    }
}
