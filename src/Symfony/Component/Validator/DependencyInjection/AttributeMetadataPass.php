<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MappingException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class AttributeMetadataPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('validator.builder')) {
            return;
        }

        $this->tagClassesWithConstraintsOnNonPublicMembers($container);

        $resolve = $container->getParameterBag()->resolveValue(...);
        $mappedClasses = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->hasTag('validator.attribute_metadata')) {
                continue;
            }
            $class = $resolve($definition->getClass());
            foreach ($definition->getTag('validator.attribute_metadata') as $attributes) {
                if ($class !== $for = $attributes['for'] ?? $class) {
                    $this->checkSourceMapsToTarget($container, $class, $for);
                }

                $mappedClasses[$for][$class] = true;
            }
        }

        if (!$mappedClasses) {
            return;
        }

        ksort($mappedClasses);

        $container->getDefinition('validator.builder')
            ->addMethodCall('addAttributeMappings', [array_map('array_keys', $mappedClasses)]);
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

    /**
     * Tags the classes that have constraint attributes on non-public members only.
     *
     * Attribute autoconfiguration looks at public members only, while the attribute loader reads all of them.
     */
    private function tagClassesWithConstraintsOnNonPublicMembers(ContainerBuilder $container): void
    {
        $autoconfigurators = method_exists($container, 'getAttributeAutoconfigurators') ? $container->getAttributeAutoconfigurators() : $container->getAutoconfiguredAttributes();

        if (!isset($autoconfigurators[Constraint::class])) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->hasTag('validator.attribute_metadata')
                || !$definition->isAutoconfigured()
                || ($definition->isAbstract() && !$definition->hasTag('container.excluded'))
                || $definition->hasTag('container.ignore_attributes')
                || !$class = $container->getReflectionClass($definition->getClass(), false)
            ) {
                continue;
            }

            foreach ($class->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE) as $member) {
                if (!$member->isStatic() && $member->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                    $definition->addTag('validator.attribute_metadata');
                    continue 2;
                }
            }

            foreach ($class->getMethods(\ReflectionMethod::IS_PROTECTED | \ReflectionMethod::IS_PRIVATE) as $member) {
                if (!$member->isConstructor() && !$member->isDestructor() && $member->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                    $definition->addTag('validator.attribute_metadata');
                    continue 2;
                }
            }
        }
    }
}
