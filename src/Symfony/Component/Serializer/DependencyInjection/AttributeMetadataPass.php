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
use Symfony\Component\Serializer\Exception\MappingException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class AttributeMetadataPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('serializer.mapping.attribute_loader')) {
            return;
        }

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
}
