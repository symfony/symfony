<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * This pass validates aliases, it provides the following checks:
 *
 * - An alias which happens to be an interface must resolve to a service implementing this interface. This ensures injecting the aliased interface won't cause a type error at runtime.
 */
class CheckAliasValidityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getAliases() as $id => $alias) {
            try {
                if (!$container->hasDefinition((string) $alias)) {
                    continue;
                }

                $target = $container->getDefinition((string) $alias);
                if (null === $target->getClass() || null !== $target->getFactory()) {
                    continue;
                }

                $reflection = $container->getReflectionClass($id);
                if (null === $reflection || !$reflection->isInterface()) {
                    continue;
                }

                $targetReflection = $container->getReflectionClass($target->getClass());
                if (null !== $targetReflection && !$targetReflection->implementsInterface($id)) {
                    throw new RuntimeException(\sprintf('Invalid alias definition: alias "%s" is referencing class "%s" but this class does not implement "%s". Because this alias is an interface, "%s" must implement "%s".', $id, $target->getClass(), $id, $target->getClass(), $id));
                }
            } catch (\ReflectionException) {
                continue;
            }
        }
    }
}
