<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes decorated object managers resettable.
 *
 * Decorating a manager makes its service id point at the decorator and turns the manager itself
 * into a private service. Resetting the decorator would only rebuild it around the very same
 * manager instance, so ManagerRegistry::resetService() resets the decorated manager instead.
 * Private services have no entry in the dumped container's method map and therefore cannot be
 * rebuilt, hence the manager is made public and its id handed over to the registry.
 *
 * This pass must run after DecoratorServicePass, i.e. at the PassConfig::TYPE_BEFORE_REMOVING stage.
 */
final class ExposeDecoratedManagersPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $registryId = 'doctrine',
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition($this->registryId)) {
            return;
        }

        $decoratedManagers = [];

        foreach ($container->getDefinitions() as $definition) {
            $decoration = $definition->getTag('container.decorator')[0] ?? null;

            if (!isset($decoration['id'], $decoration['inner']) || !$container->hasDefinition($decoration['inner'])) {
                continue;
            }

            $innerDefinition = $container->getDefinition($decoration['inner']);

            if (!$class = $innerDefinition->getClass()) {
                continue;
            }

            if (!$container->getReflectionClass($class, false)?->implementsInterface(ObjectManager::class)) {
                continue;
            }

            // the registry rebuilds the manager through the container, which only knows public services
            $innerDefinition->setPublic(true);

            $decoratedManagers[$decoration['id']] = $decoration['inner'];
        }

        if ($decoratedManagers) {
            $container->getDefinition($this->registryId)->addMethodCall('setDecoratedManagers', [$decoratedManagers]);
        }
    }
}
