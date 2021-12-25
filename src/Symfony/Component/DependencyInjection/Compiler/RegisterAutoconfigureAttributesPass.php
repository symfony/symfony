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

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Reads #[Autoconfigure] attributes on definitions that are autoconfigured
 * and don't have the "container.ignore_attributes" tag.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class RegisterAutoconfigureAttributesPass implements CompilerPassInterface
{
    private static $registerForAutoconfiguration;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->accept($definition) && $class = $container->getReflectionClass($definition->getClass(), false)) {
                $this->processClass($container, $class);
            }
        }
    }

    public function accept(Definition $definition): bool
    {
        return $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes');
    }

    public function processClass(ContainerBuilder $container, \ReflectionClass $class)
    {
        foreach ($class->getAttributes(Autoconfigure::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            self::registerForAutoconfiguration($container, $class, $attribute);
        }
    }

    private static function registerForAutoconfiguration(ContainerBuilder $container, \ReflectionClass $class, \ReflectionAttribute $attribute)
    {
        if (self::$registerForAutoconfiguration) {
            return (self::$registerForAutoconfiguration)($container, $class, $attribute);
        }

        $parseDefinitions = new \ReflectionMethod(YamlFileLoader::class, 'parseDefinitions');
        $parseDefinitions->setAccessible(true);
        $yamlLoader = $parseDefinitions->getDeclaringClass()->newInstanceWithoutConstructor();

        self::$registerForAutoconfiguration = static function (ContainerBuilder $container, \ReflectionClass $class, \ReflectionAttribute $attribute) use ($parseDefinitions, $yamlLoader) {
            $attribute = (array) $attribute->newInstance();

            foreach ($attribute['tags'] ?? [] as $i => $tag) {
                if (\is_array($tag) && [0] === array_keys($tag)) {
                    $attribute['tags'][$i] = [$class->name => $tag[0]];
                }
            }

            $parseDefinitions->invoke(
                $yamlLoader,
                [
                    'services' => [
                        '_instanceof' => [
                            $class->name => [$container->registerForAutoconfiguration($class->name)] + $attribute,
                        ],
                    ],
                ],
                $class->getFileName()
            );
        };

        return (self::$registerForAutoconfiguration)($container, $class, $attribute);
    }
}
