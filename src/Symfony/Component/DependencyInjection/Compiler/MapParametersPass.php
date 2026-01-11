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

use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\Attribute\MapParameters;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Processes classes with the MapParameters attribute to map parameters.
 *
 * Supports scalar types (string, int, float, bool), arrays, backed enums,
 * DateTimeInterface objects, and nested object types.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
class MapParametersPass implements CompilerPassInterface
{
    private array $hydrationStack = [];
    private array $reflectionCache = [];
    private array $errors = [];

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('di.map_parameters', true) as $serviceId => $attributes) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?: $serviceId;
            $definition->setClass($class);

            if (!$reflectionClass = $container->getReflectionClass($class)) {
                continue;
            }

            if (!$mapConfigAttributes = $reflectionClass->getAttributes(MapParameters::class)) {
                continue;
            }

            try {
                $this->hydrationStack = [];
                $this->configureDefinition($container, $definition, $reflectionClass, $mapConfigAttributes[0]->newInstance());
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            } finally {
                $this->hydrationStack = [];
            }
        }

        if ($this->errors) {
            throw new InvalidArgumentException(\sprintf("Parameter Mapping errors found:\n\n%s.", implode("\n\n", $this->errors)));
        }
    }

    private function configureDefinition(ContainerBuilder $container, Definition $definition, \ReflectionClass $reflectionClass, MapParameters $attribute): void
    {
        $definition->setAutowired(true);
        $configArray = $this->resolveConfigPath($container, $attribute->path);
        $container->addResource(new ClassExistenceResource($reflectionClass->getName()));

        if (\is_array($configArray)) {
            $this->hydrateDefinition($container, $definition, $reflectionClass, $configArray, $attribute->path);
        } else {
            $this->mapFlatParameters($container, $definition, $reflectionClass, $attribute->path);
        }
    }

    private function hydrateDefinition(ContainerBuilder $container, Definition $definition, \ReflectionClass $reflectionClass, array $configArray, string $contextPath): void
    {
        if (isset($this->hydrationStack[$className = $reflectionClass->getName()])) {
            throw new \RuntimeException(\sprintf('Circular reference detected for class "%s".', $className));
        }

        $this->hydrationStack[$className] = true;

        try {
            $normalizedConfig = [];
            foreach ($configArray as $key => $value) {
                $normalizedConfig[$this->normalizeKey($key)] = ['originalKey' => $key, 'value' => $value];
            }

            $usedKeys = $reflectionClass->getConstructor()
                ? $this->mapConstructorParams($container, $definition, $reflectionClass->getConstructor(), $normalizedConfig, $contextPath)
                : [];

            $this->mapProperties($container, $definition, $reflectionClass, $normalizedConfig, $usedKeys, $contextPath);
        } finally {
            unset($this->hydrationStack[$className]);
        }
    }

    private function mapConstructorParams(ContainerBuilder $container, Definition $definition, \ReflectionMethod $constructor, array $normalizedConfig, string $path): array
    {
        $usedKeys = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            if (\array_key_exists($paramName, $definition->getArguments()) || \array_key_exists('$'.$paramName, $definition->getArguments())) {
                continue;
            }

            $normalizedParamName = $this->normalizeKey($paramName);

            if (\array_key_exists($normalizedParamName, $normalizedConfig)) {
                $match = $normalizedConfig[$normalizedParamName];

                if (null === $match['value'] && $parameter->isDefaultValueAvailable()) {
                    $usedKeys[$match['originalKey']] = true;
                    continue;
                }

                $definition->setArgument('$'.$paramName, $this->resolveValue($container, $match['value'], $parameter, $path.'.'.$match['originalKey']));
                $usedKeys[$match['originalKey']] = true;
            } elseif (!$parameter->isDefaultValueAvailable()) {
                throw new InvalidArgumentException(\sprintf('The parameter "$%s" is missing in path "%s".', $paramName, $path));
            }
        }

        return $usedKeys;
    }

    private function mapProperties(ContainerBuilder $container, Definition $definition, \ReflectionClass $reflectionClass, array $normalizedConfig, array $usedKeys, string $contextPath): void
    {
        $extraKeys = [];
        $inaccessibleKeys = [];

        foreach ($normalizedConfig as $camelCaseKey => $info) {
            if (isset($usedKeys[$info['originalKey']])) {
                continue;
            }

            if ($this->tryMapSetter($container, $definition, $reflectionClass, $camelCaseKey, $info, $contextPath)) {
                continue;
            }

            if ($this->tryMapProperty($container, $definition, $reflectionClass, $camelCaseKey, $info['originalKey'], $info['value'], $contextPath)) {
                continue;
            }

            // Check if property exists but is inaccessible
            $propertyExists = false;
            foreach ([$info['originalKey'], $camelCaseKey] as $propName) {
                if ($reflectionClass->hasProperty($propName)) {
                    $propertyExists = true;
                    $prop = $reflectionClass->getProperty($propName);
                    $visibility = $prop->isPrivate() ? 'private' : ($prop->isProtected() ? 'protected' : 'public');
                    $inaccessibleKeys[$info['originalKey']] = \sprintf(
                        'property is %s and has no setter',
                        $visibility
                    );
                    break;
                }
            }

            if (!$propertyExists) {
                $extraKeys[] = $info['originalKey'];
            }
        }

        if ($extraKeys) {
            $count = \count($extraKeys);
            throw new InvalidArgumentException(\sprintf(
                'Cannot configure service "%s": the parameter%s "%s" in path "%s" %s not recognized.',
                $reflectionClass->getName(),
                $count > 1 ? 's' : '',
                implode('", "', $extraKeys),
                $contextPath,
                $count > 1 ? 'are' : 'is'
            ));
        }

        if ($inaccessibleKeys) {
            $key = array_key_first($inaccessibleKeys);
            $reason = $inaccessibleKeys[$key];
            throw new InvalidArgumentException(\sprintf('Cannot configure service "%s": parameter "%s" in path "%s" is not accessible (%s).', $reflectionClass->getName(), $key, $contextPath, $reason));
        }
    }

    private function tryMapSetter(ContainerBuilder $container, Definition $definition, \ReflectionClass $reflectionClass, string $camelCaseKey, array $info, string $contextPath): bool
    {
        $setterName = 'set'.ucfirst($camelCaseKey);

        if (!$reflectionClass->hasMethod($setterName)) {
            return false;
        }

        $method = $reflectionClass->getMethod($setterName);
        if (!$method->isPublic() || $method->isStatic() || 1 !== $method->getNumberOfParameters()) {
            return false;
        }

        $definition->addMethodCall($setterName, [$this->resolveValue($container, $info['value'], $method->getParameters()[0], $contextPath.'.'.$info['originalKey'])]);

        return true;
    }

    private function tryMapProperty(ContainerBuilder $container, Definition $definition, \ReflectionClass $reflectionClass, string $camelCaseKey, string $originalKey, mixed $value, string $contextPath): bool
    {
        foreach ([$originalKey, $camelCaseKey] as $propName) {
            if (!$reflectionClass->hasProperty($propName)) {
                continue;
            }

            $prop = $reflectionClass->getProperty($propName);

            if ($prop->isPublic() && !$prop->isReadOnly() && !$prop->isProtectedSet() && !$prop->isPrivateSet()) {
                $definition->setProperty($propName, $this->resolveValue($container, $value, $prop, $contextPath.'.'.$originalKey));

                return true;
            }

            if ($prop->isPublic() && method_exists($prop, 'hasHooks') && $prop->hasHooks() && $prop->hasHook(\PropertyHookType::Set)) {
                $definition->setProperty($propName, $this->resolveValue($container, $value, $prop, $contextPath.'.'.$originalKey));

                return true;
            }
        }

        return false;
    }

    private function resolveValue(ContainerBuilder $container, mixed $value, \ReflectionParameter|\ReflectionProperty $target, string $contextPath): mixed
    {
        $className = $this->getClassName($target);

        // Handle backed enums
        if ($className && enum_exists($className) && $this->getEnumReflection($className)?->isBacked()) {
            return (new Definition($className))->setFactory([$className, 'from'])->setArguments([$value]);
        }

        // Handle DateTime classes
        if (is_a($className, \DateTimeInterface::class, true)) {
            if (\is_string($value) && ctype_digit($value) && 4 === \strlen($value)) {
                $value .= '-01-01';
            }

            return new Definition(
                \DateTimeInterface::class === $className ? \DateTimeImmutable::class : $className,
                [$value]
            );
        }

        // Handle nested objects
        if (\is_array($value) && $className && (class_exists($className) || interface_exists($className, false))) {
            return $this->resolveNestedObject($container, $value, $className, $contextPath);
        }

        // Type mismatch check
        if (!\is_array($value) && $className && (class_exists($className) || interface_exists($className, false))) {
            throw new InvalidArgumentException(\sprintf('The path "%s" must be an array, got "%s".', $contextPath, get_debug_type($value)));
        }

        return $value;
    }

    private function getClassName(\ReflectionParameter|\ReflectionProperty $target): ?string
    {
        $type = $target->getType();

        return ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) ? $type->getName() : null;
    }

    private function resolveNestedObject(ContainerBuilder $container, array $value, string $className, string $contextPath): Definition
    {
        if (!$nestedClass = $container->getReflectionClass($className)) {
            return new Definition($className);
        }

        $nestedDefinition = (new Definition($className))->setAutowired(true);
        $this->hydrateDefinition($container, $nestedDefinition, $nestedClass, $value, $contextPath);

        return $nestedDefinition;
    }

    private function resolveConfigPath(ContainerBuilder $container, string $path): mixed
    {
        $parameterBag = $container->getParameterBag();

        if ($parameterBag->has($path)) {
            return $parameterBag->get($path);
        }

        if (str_contains($path, '.')) {
            [$paramName, $subPath] = explode('.', $path, 2);
            if ($parameterBag->has($paramName)) {
                return $this->getNestedValue($parameterBag->get($paramName), $subPath);
            }
        }

        return null;
    }

    private function getNestedValue(mixed $array, string $path): mixed
    {
        if (!\is_array($array)) {
            return null;
        }

        foreach (explode('.', $path) as $key) {
            if (!\is_array($array)) {
                return null;
            }

            // Handle both string and numeric keys
            if (!\array_key_exists($key, $array) && !\array_key_exists((int) $key, $array)) {
                return null;
            }

            $array = $array[$key] ?? $array[(int) $key];
        }

        return $array;
    }

    private function mapFlatParameters(ContainerBuilder $container, Definition $definition, \ReflectionClass $reflectionClass, string $path): void
    {
        if (!$constructor = $reflectionClass->getConstructor()) {
            return;
        }

        $parameterBag = $container->getParameterBag();

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            if (\array_key_exists($paramName, $definition->getArguments()) || \array_key_exists('$'.$paramName, $definition->getArguments())) {
                continue;
            }

            $candidates = [$path.'.'.$paramName, $path.'.'.strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $paramName))];

            foreach ($candidates as $key) {
                if ($parameterBag->has($key)) {
                    $definition->setArgument('$'.$paramName, $parameterBag->get($key));
                    continue 2;
                }
            }

            if (!$parameter->isDefaultValueAvailable()) {
                throw new InvalidArgumentException(\sprintf('The parameter "$%s" of the class "%s" cannot be resolved: the container parameter "%s" was not found.', $paramName, $reflectionClass->getName(), $candidates[0]));
            }
        }
    }

    private function normalizeKey(string $input): string
    {
        return lcfirst(str_replace(['_', '-'], '', ucwords($input, '_-')));
    }

    private function getEnumReflection(string $className): ?\ReflectionEnum
    {
        return $this->reflectionCache[$className] ??= enum_exists($className) ? new \ReflectionEnum($className) : null;
    }
}
