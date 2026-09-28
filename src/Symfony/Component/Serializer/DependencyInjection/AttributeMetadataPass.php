<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Serializer\Exception\MappingException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class AttributeMetadataPass implements CompilerPassInterface
{
    private const MEMBER_ATTRIBUTES = [
        Context::class,
        Groups::class,
        Ignore::class,
        MaxDepth::class,
        SerializedName::class,
        SerializedPath::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('serializer.mapping.attribute_loader')) {
            return;
        }

        $this->tagClassesWithAttributesOnNonPublicMembers($container);

        $resolve = $container->getParameterBag()->resolveValue(...);
        $taggedClasses = [];
        $discriminatorMapTypes = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->hasTag('serializer.attribute_metadata')) {
                continue;
            }
            $class = $resolve($definition->getClass());
            foreach ($definition->getTag('serializer.attribute_metadata') as $attributes) {
                $for = $attributes['for'] ?? $class;

                if ($attributes['discriminator_map_type'] ?? false) {
                    $this->checkDiscriminatorMapType($container, $class, $for);
                    $type = $attributes['type'];
                    if (isset($discriminatorMapTypes[$for][$type]) && $class !== $discriminatorMapTypes[$for][$type]) {
                        throw new MappingException(\sprintf('Discriminator map type "%s" for "%s" is already mapped to "%s".', $type, $for, $discriminatorMapTypes[$for][$type]));
                    }
                    $discriminatorMapTypes[$for][$type] = $class;

                    continue;
                }

                if ($class !== $for) {
                    $this->checkSourceMapsToTarget($container, $class, $for);
                }

                $taggedClasses[$for][$class] = true;
            }
        }

        ksort($discriminatorMapTypes);
        $loader = $container->getDefinition('serializer.mapping.attribute_loader')
            ->setArgument(2, $discriminatorMapTypes);

        if ($container->hasDefinition('property_info.cache_warmer')) {
            $this->addClassesToPropertyInfoCacheWarmer($container, array_keys($taggedClasses + $discriminatorMapTypes));
        }

        if (!$taggedClasses) {
            return;
        }

        ksort($taggedClasses);
        $loader->replaceArgument(1, array_map('array_keys', $taggedClasses));
    }

    private function checkSourceMapsToTarget(ContainerBuilder $container, string $source, string $target): void
    {
        if (!$r = $container->getReflectionClass($source)) {
            throw new MappingException(\sprintf('Class "%s" cannot extend serialization for "%s" because it cannot be found.', $source, $target));
        }
        $source = $r;
        if (!$r = $container->getReflectionClass($target)) {
            throw new MappingException(\sprintf('Class "%s" cannot extend serialization for "%s" because the target class cannot be found.', $source->name, $target));
        }
        $target = $r;

        foreach ($source->getProperties() as $p) {
            if ($p->class === $source->name && !($target->hasProperty($p->name) && $target->getProperty($p->name)->class === $target->name)) {
                throw new MappingException(\sprintf('The property "%s" on "%s" is not present on "%s".', $p->name, $source->name, $target->name));
            }
        }

        foreach ($source->getMethods() as $m) {
            if ($m->class === $source->name && !($target->hasMethod($m->name) && $target->getMethod($m->name)->class === $target->name)) {
                throw new MappingException(\sprintf('The method "%s" on "%s" is not present on "%s".', $m->name, $source->name, $target->name));
            }
        }
    }

    private function checkDiscriminatorMapType(ContainerBuilder $container, string $source, string $target): void
    {
        if (!$r = $container->getReflectionClass($source)) {
            throw new MappingException(\sprintf('Class "%s" cannot add a discriminator map type for "%s" because it cannot be found.', $source, $target));
        }
        $source = $r;
        if (!$r = $container->getReflectionClass($target)) {
            throw new MappingException(\sprintf('Class "%s" cannot add a discriminator map type for "%s" because the target class cannot be found.', $source->name, $target));
        }
        $target = $r;

        if (!is_a($source->name, $target->name, true)) {
            throw new MappingException(\sprintf('Class "%s" cannot add a discriminator map type for "%s" because it is not a subtype of it.', $source->name, $target->name));
        }
    }

    /**
     * @param class-string[] $classes
     */
    private function addClassesToPropertyInfoCacheWarmer(ContainerBuilder $container, array $classes): void
    {
        if ($container->hasDefinition('serializer.mapping.chain_loader')) {
            // the classes mapped by files, collected per loader
            foreach ($container->getDefinition('serializer.mapping.chain_loader')->getArgument(1) as $mappedClasses) {
                $classes = array_merge($classes, array_keys($mappedClasses));
            }
        }

        $warmer = $container->getDefinition('property_info.cache_warmer');
        $classes = array_unique(array_merge($warmer->getArgument(1), $classes));
        sort($classes);
        $warmer->replaceArgument(1, $classes);
    }

    /**
     * Tags the classes that have serialization attributes on non-public members only.
     *
     * Attribute autoconfiguration looks at public members only, while the attribute loader reads all of them.
     */
    private function tagClassesWithAttributesOnNonPublicMembers(ContainerBuilder $container): void
    {
        $autoconfigurators = $container->getAttributeAutoconfigurators();

        if (!$attributes = array_intersect(self::MEMBER_ATTRIBUTES, array_keys($autoconfigurators))) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if (!$definition->isAutoconfigured()
                || ($definition->isAbstract() && !$definition->hasTag('container.excluded'))
                || $definition->hasTag('container.ignore_attributes')
            ) {
                continue;
            }

            // contributing to a discriminator map does not map the attributes of the class itself
            foreach ($definition->getTag('serializer.attribute_metadata') as $tag) {
                if (!($tag['discriminator_map_type'] ?? false)) {
                    continue 2;
                }
            }

            if (!$class = $container->getReflectionClass($definition->getClass(), false)) {
                continue;
            }

            foreach ($class->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE) as $member) {
                if (!$member->isStatic() && $this->hasAttribute($member, $attributes)) {
                    $definition->addTag('serializer.attribute_metadata');
                    continue 2;
                }
            }

            foreach ($class->getMethods(\ReflectionMethod::IS_PROTECTED | \ReflectionMethod::IS_PRIVATE) as $member) {
                if (!$member->isConstructor() && !$member->isDestructor() && $this->hasAttribute($member, $attributes)) {
                    $definition->addTag('serializer.attribute_metadata');
                    continue 2;
                }
            }
        }
    }

    /**
     * @param class-string[] $attributes
     */
    private function hasAttribute(\ReflectionProperty|\ReflectionMethod $member, array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if ($member->getAttributes($attribute, \ReflectionAttribute::IS_INSTANCEOF)) {
                return true;
            }
        }

        return false;
    }
}
