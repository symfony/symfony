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
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->hasTag('serializer.attribute_metadata')) {
                continue;
            }
            $class = $resolve($definition->getClass());
            foreach ($definition->getTag('serializer.attribute_metadata') as $attributes) {
                if ($class !== $for = $attributes['for'] ?? $class) {
                    $this->checkSourceMapsToTarget($container, $class, $for);
                }

                $taggedClasses[$for][$class] = true;
            }
        }

        if (!$taggedClasses) {
            return;
        }

        ksort($taggedClasses);

        $container->getDefinition('serializer.mapping.attribute_loader')
            ->replaceArgument(1, array_map('array_keys', $taggedClasses));
    }

    private function checkSourceMapsToTarget(ContainerBuilder $container, string $source, string $target): void
    {
        $source = $container->getReflectionClass($source);
        $target = $container->getReflectionClass($target);

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
}
