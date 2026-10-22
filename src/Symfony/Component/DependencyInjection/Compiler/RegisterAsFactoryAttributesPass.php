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

use Symfony\Component\DependencyInjection\Attribute\AsFactory;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class RegisterAsFactoryAttributesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $factories = new \WeakMap();
        $pending = $container->getDefinitions();
        $generated = false;

        do {
            $definitions = $pending;
            $pending = [];

            foreach ($definitions as $id => $definition) {
                if (!$definition->isAutoconfigured() || $definition->isAbstract() || $definition->hasTag('container.ignore_attributes')) {
                    continue;
                }

                $class = $this->getInheritedDefinition($container, $definition, 'class')->getClass();
                if (!$class = $container->getReflectionClass($class, false)) {
                    continue;
                }

                foreach ($class->getMethods() as $method) {
                    if (!$attributes = $method->getAttributes(AsFactory::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                        continue;
                    }

                    if (!$method->isPublic()) {
                        throw new InvalidArgumentException(\sprintf('The "#[AsFactory]" attribute cannot be used on the non-public method "%s::%s()".', $class->name, $method->name));
                    }

                    $returnType = $method->getReturnType();
                    if (!$returnType instanceof \ReflectionNamedType || $returnType->isBuiltin() || $returnType->allowsNull()) {
                        throw new InvalidArgumentException(\sprintf('The "#[AsFactory]" method "%s::%s()" must return a non-nullable class or interface type.', $class->name, $method->name));
                    }

                    $type = match ($returnType->getName()) {
                        'self' => $method->getDeclaringClass()->name,
                        'parent' => $method->getDeclaringClass()->getParentClass()->name,
                        'static' => $class->name,
                        default => $returnType->getName(),
                    };

                    if (!$container->getReflectionClass($type, false)) {
                        throw new InvalidArgumentException(\sprintf('The "#[AsFactory]" method "%s::%s()" has an unknown return type "%s".', $class->name, $method->name, $type));
                    }

                    $attribute = $attributes[0]->newInstance();
                    $serviceId = $attribute->id ?? $type;

                    if ('' === $serviceId) {
                        throw new InvalidArgumentException(\sprintf('The service id declared by "#[AsFactory]" on "%s::%s()" cannot be empty.', $class->name, $method->name));
                    }

                    if ($container->has($serviceId)) {
                        $service = $container->findDefinition($serviceId);
                    } else {
                        $service = $pending[$serviceId] = $container->register($serviceId, $type)
                            ->setAutowired(true)
                            ->setAutoconfigured(true);
                    }

                    $declaringMethod = $method->getDeclaringClass()->name.'::'.$method->name;
                    if (isset($factories[$service])) {
                        if ($generated && $service === $definition && $factories[$service] === $declaringMethod) {
                            continue;
                        }

                        throw new InvalidArgumentException(\sprintf('Cannot register the factory declared by "#[AsFactory]" on "%s::%s()" for service "%s" because another factory already creates it.', $class->name, $method->name, $serviceId));
                    }

                    if ($service->isAbstract() || $service->isSynthetic()) {
                        throw new InvalidArgumentException(\sprintf('The service "%s" declared by "#[AsFactory]" on "%s::%s()" cannot be abstract or synthetic.', $serviceId, $class->name, $method->name));
                    }

                    $factory = [$method->isStatic() ? $class->name : new Reference($id), $method->name];
                    $existingFactory = $this->getInheritedDefinition($container, $service, 'factory')->getFactory();
                    if (null !== $existingFactory && $existingFactory != $factory) {
                        throw new InvalidArgumentException(\sprintf('The service "%s" declared by "#[AsFactory]" on "%s::%s()" already has a factory.', $serviceId, $class->name, $method->name));
                    }

                    $service->setClass($this->getInheritedDefinition($container, $service, 'class')->getClass() ?? $type);
                    $service->setFactory($factory);
                    $factories[$service] = $declaringMethod;
                }
            }

            $generated = true;
        } while ($pending);
    }

    private function getInheritedDefinition(ContainerBuilder $container, Definition $definition, string $property): Definition
    {
        while ($definition instanceof ChildDefinition && !isset($definition->getChanges()[$property]) && $container->has($definition->getParent())) {
            $definition = $container->findDefinition($definition->getParent());
        }

        return $definition;
    }
}
