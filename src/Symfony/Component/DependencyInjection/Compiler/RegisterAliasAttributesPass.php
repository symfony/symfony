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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Attribute\Alias as AliasAttribute;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Reads #[Alias] attributes on definitions which don't have the
 * "container.ignore_attributes" tag.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class RegisterAliasAttributesPass implements CompilerPassInterface
{
    private static $registerForAliasing;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (80000 > \PHP_VERSION_ID) {
            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->accept($definition) && $definition->isAutoconfigured() && null !== $class = $container->getReflectionClass($definition->getClass())) {
                $this->processClass($container, $class);
            }
        }
    }

    public function accept(Definition $definition): bool
    {
        return 80000 <= \PHP_VERSION_ID && !$definition->hasTag('container.ignore_attributes');
    }

    public function processClass(ContainerBuilder $container, \ReflectionClass $class)
    {
        foreach ($class->getAttributes(AliasAttribute::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            self::registerForAliasing($container, $class, $attribute);
        }
    }

    private static function registerForAliasing(ContainerBuilder $container, \ReflectionClass $class, \ReflectionAttribute $attribute): void
    {
        if (self::$registerForAliasing) {
            (self::$registerForAliasing)($container, $class, $attribute);

            return;
        }

        self::$registerForAliasing = static function (ContainerBuilder $container, \ReflectionClass $class, \ReflectionAttribute $attribute) {
            $attribute = (array) $attribute->newInstance();
            $container->setAlias($attribute['name'], new Alias($class->name));
        };

        (self::$registerForAliasing)($container, $class, $attribute);
    }
}
