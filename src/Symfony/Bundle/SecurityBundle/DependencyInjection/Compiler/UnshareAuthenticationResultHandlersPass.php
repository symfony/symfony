<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Unshares the handlers wired into authenticators, since each of them configures
 * its own with its options, together with the services decorating them.
 */
final class UnshareAuthenticationResultHandlersPass implements CompilerPassInterface
{
    private const HANDLER_PARENTS = [
        'security.authentication.custom_success_handler',
        'security.authentication.custom_failure_handler',
    ];

    public function process(ContainerBuilder $container): void
    {
        $decorators = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($decorated = $definition->getDecoratedService()) {
                $decorators[$decorated[0]][] = $id;
            }
        }

        $unshared = [];

        foreach ($container->getDefinitions() as $definition) {
            if (!$definition instanceof ChildDefinition || !\in_array($definition->getParent(), self::HANDLER_PARENTS, true)) {
                continue;
            }

            if (($handler = $definition->getArguments()['index_0'] ?? null) instanceof Reference) {
                $this->unshare($container, (string) $handler, $decorators, $unshared);
            }
        }
    }

    private function unshare(ContainerBuilder $container, string $id, array $decorators, array &$unshared): void
    {
        if ($unshared[$id] ?? false) {
            return;
        }
        $unshared[$id] = true;

        foreach ($decorators[$id] ?? [] as $decoratorId) {
            $this->unshare($container, $decoratorId, $decorators, $unshared);
        }

        if ($container->hasAlias($id)) {
            $this->unshare($container, (string) $container->getAlias($id), $decorators, $unshared);

            return;
        }

        if (!$container->hasDefinition($id)) {
            return;
        }

        $definition = $container->getDefinition($id);
        $definition->setShared(false);

        if (!$definition->hasTag('container.stack')) {
            return;
        }

        // the stack is turned into a chain of decorators later on, unshare each of its layers
        foreach ($definition->getArguments() as $argument) {
            if ($argument instanceof Definition) {
                $argument->setShared(false);
            } elseif ($argument instanceof Reference || $argument instanceof Alias) {
                $this->unshare($container, (string) $argument, $decorators, $unshared);
            }
        }
    }
}
