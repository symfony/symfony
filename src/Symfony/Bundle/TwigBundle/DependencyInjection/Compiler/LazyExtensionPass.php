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
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Makes the extensions of the Twig environment lazy, so that their dependencies are created on first use.
 *
 * @internal
 */
final class LazyExtensionPass implements CompilerPassInterface
{
    private static bool $supportsRuntimeExtensions;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig')) {
            return;
        }

        $twig = $container->getDefinition('twig');
        $calls = $twig->getMethodCalls();

        foreach ($calls as $i => [$method, $arguments]) {
            if ('addExtension' !== $method || !($arguments[0] ?? null) instanceof Reference || !$container->has($arguments[0])) {
                continue;
            }

            $definition = $container->findDefinition($arguments[0]);

            // Keep the laziness that the service declares, including "lazy: false"
            if ($definition->isLazy() || isset($definition->getChanges()['lazy'])) {
                continue;
            }

            if (!$r = $container->getReflectionClass($definition->getClass(), false)) {
                continue;
            }

            if (self::declaresOnlyAttributeCallables($r)) {
                $calls[$i][1][0] = new Definition(AttributeExtension::class, [$r->name]);
                $definition->addTag('twig.runtime');

                continue;
            }

            // An extension that doesn't depend on other services is cheaper to create than a lazy object
            if (!self::hasReferences([$definition->getArguments(), $definition->getProperties(), $definition->getMethodCalls(), $definition->getConfigurator()])) {
                continue;
            }

            // Twig identifies extensions by class, so the lazy object must be an instance of the declared class.
            // A factory doesn't guarantee that, and PHP cannot make an object lazy when its class inherits an internal one.
            if ($definition->getFactory()) {
                continue;
            }

            do {
                if ($r->isInternal()) {
                    continue 2;
                }
            } while ($r = $r->getParentClass());

            $definition->setLazy(true);
        }

        $twig->setMethodCalls($calls);
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

    /**
     * Tells whether the extension can be registered through AttributeExtension and created on first call, like a runtime.
     */
    private static function declaresOnlyAttributeCallables(\ReflectionClass $r): bool
    {
        if (!$r->isSubclassOf(AbstractExtension::class) || $r->implementsInterface(GlobalsInterface::class)) {
            return false;
        }

        $declaresCallables = false;
        foreach ((new \ReflectionClass(AbstractExtension::class))->getMethods() as $method) {
            if (AbstractExtension::class === $r->getMethod($method->name)->class) {
                continue;
            }
            if (!\in_array($method->name, ['getFunctions', 'getFilters', 'getTests'], true)) {
                return false;
            }
            $declaresCallables = true;
        }

        // On older Twig versions, Environment::getExtension() would return the AttributeExtension instead of the extension,
        // so only extensions that have no other way to declare their callables are registered this way
        if ($declaresCallables && !self::supportsRuntimeExtensions()) {
            return false;
        }

        foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(AsTwigFunction::class) || $method->getAttributes(AsTwigFilter::class) || $method->getAttributes(AsTwigTest::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether Environment::getExtension() returns the runtime of an extension class registered through AttributeExtension, as Twig 3.31 does.
     */
    private static function supportsRuntimeExtensions(): bool
    {
        if (isset(self::$supportsRuntimeExtensions)) {
            return self::$supportsRuntimeExtensions;
        }

        $extension = new class extends AbstractExtension {};
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension(new AttributeExtension($extension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([$extension::class => static fn () => $extension]));

        return self::$supportsRuntimeExtensions = $extension === $twig->getExtension($extension::class);
    }
}
