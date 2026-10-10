<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Makes the extensions of the Twig environment lazy, so that their dependencies are created on first use.
 *
 * @internal
 */
final class LazyExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig')) {
            return;
        }

        foreach ($container->getDefinition('twig')->getMethodCalls() as [$method, $arguments]) {
            if ('addExtension' !== $method || !($arguments[0] ?? null) instanceof Reference || !$container->has($arguments[0])) {
                continue;
            }

            $definition = $container->findDefinition($arguments[0]);

            // Keep the laziness that the service declares, including "lazy: false"
            if ($definition->isLazy() || isset($definition->getChanges()['lazy'])) {
                continue;
            }

            // An extension that doesn't depend on other services is cheaper to create than a lazy object
            if (!self::hasReferences([$definition->getArguments(), $definition->getProperties(), $definition->getMethodCalls(), $definition->getConfigurator()])) {
                continue;
            }

            // Twig identifies extensions by class, so the lazy object must be an instance of the declared class.
            // A factory doesn't guarantee that, and PHP cannot make an object lazy when its class inherits an internal one.
            if ($definition->getFactory() || !$r = $container->getReflectionClass($container->getParameterBag()->resolveValue($definition->getClass()), false)) {
                continue;
            }

            // An extension that has a runtime class, named after Twig's convention, already defers its dependencies to that runtime
            if (str_ends_with($r->name, 'Extension') && $container->getReflectionClass(substr($r->name, 0, -9).'Runtime', false)) {
                continue;
            }

            do {
                if ($r->isInternal()) {
                    continue 2;
                }
            } while ($r = $r->getParentClass());

            $definition->setLazy(true);
        }
    }

    private static function hasReferences(array $values): bool
    {
        foreach ($values as $value) {
            if ($value instanceof Reference || $value instanceof Definition || (\is_array($value) && self::hasReferences($value))) {
                return true;
            }
        }

        return false;
    }
}
