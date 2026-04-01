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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Reads #[AsFactory] attributes on autoconfigured service definitions and registers
 * a factory with the declared services, accordingly.
 */
final class RegisterAsFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $factoryId => $definition) {
            if ($definition->hasTag('container.ignore_attributes') || $definition->hasTag('container.excluded') || !$definition->isAutoconfigured() || $definition->isAbstract()) {
                continue;
            }

            $class = $container->getReflectionClass($definition->getClass(), false);
            if (null === $class) {
                continue;
            }

            foreach ($class->getAttributes(AsFactory::class) as $attribute) {
                $this->configureFactory($container, $factoryId, $attribute->newInstance(), $class, null);
            }

            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isDestructor()) {
                    continue;
                }

                foreach ($method->getAttributes(AsFactory::class) as $attribute) {
                    $this->configureFactory($container, $factoryId, $attribute->newInstance(), $class, $method);
                }
            }
        }
    }

    private function configureFactory(
        ContainerBuilder $container,
        string $factoryId,
        AsFactory $attribute,
        \ReflectionClass $class,
        ?\ReflectionMethod $method,
    ): void {
        if (null === $method) {
            if (!$class->hasMethod('__invoke')) {
                throw new LogicException(\sprintf('The #[AsFactory] attribute on class "%s" requires the class to be invokable (add a "__invoke" method).', $class->name));
            }
            $method = $class->getMethod('__invoke');
            $factoryCallable = [new Reference($factoryId), '__invoke'];
        } else {
            $factoryCallable = $method->isStatic()
                ? [$class->name, $method->name]
                : [new Reference($factoryId), $method->name];
        }

        [$serviceId, $serviceClass] = $this->extractServiceDefinition($attribute, $method, $class);

        if ($container->hasAlias($serviceId)) {
            throw new LogicException(\sprintf('The #[AsFactory] attribute on "%s" declares service "%s", which is already defined as an alias.', $factoryId, $serviceId));
        }

        if ($container->hasDefinition($serviceId)) {
            $definition = $container->getDefinition($serviceId);
            if ($definition->isAbstract() || $definition->hasTag('container.excluded')) {
                return;
            }
            if (null !== $definition->getFactory()) {
                throw new LogicException(\sprintf('The #[AsFactory] attribute on "%s" declares service "%s", which already has a factory; remove one of them.', $factoryId, $serviceId));
            }
            $definition->setFactory($factoryCallable);
        } else {
            $container->register($serviceId, $serviceClass)
                ->setAutowired(true)
                ->setFactory($factoryCallable);
        }
    }

    /**
     * @return list{string, class-string}
     */
    private function extractServiceDefinition(AsFactory $attribute, \ReflectionMethod $method, \ReflectionClass $class): array
    {
        $serviceId = $attribute->id;

        $returnType = $method->getReturnType();

        if ('' === $serviceId) {
            if (null === $returnType) {
                throw new LogicException(\sprintf('The #[AsFactory] attribute on "%s::%s()" requires either an explicit "id" or a return type declaration.', $class->name, $method->name));
            }
            if ($returnType instanceof \ReflectionUnionType || $returnType instanceof \ReflectionIntersectionType) {
                throw new LogicException(\sprintf('The #[AsFactory] attribute on "%s::%s()" does not support union or intersection return types; provide an explicit "id".', $class->name, $method->name));
            }
            /** @var \ReflectionNamedType $returnType */
            if ($returnType->isBuiltin()) {
                throw new LogicException(\sprintf('The #[AsFactory] attribute on "%s::%s()" requires a class or interface return type, got "%s"; provide an explicit "id".', $class->name, $method->name, $returnType->getName()));
            }
            $serviceId = $this->extractDeclaringClass($returnType, $class, $method);

            return [$serviceId, $serviceId];
        }

        if ($returnType instanceof \ReflectionNamedType && !$returnType->isBuiltin()) {
            return [$serviceId, $this->extractDeclaringClass($returnType, $class, $method)];
        }

        return [$serviceId, $serviceId];
    }

    /** @return class-string */
    private function extractDeclaringClass(\ReflectionIntersectionType|\ReflectionNamedType|\ReflectionUnionType $returnType, \ReflectionClass $class, \ReflectionMethod $method): string
    {
        return match (strtolower($name = $returnType->getName())) {
            'static' => $class->name,
            'self' => $method->getDeclaringClass()->name,
            'parent' => $method->getDeclaringClass()->getParentClass()->name,
            default => $name,
        };
    }
}
