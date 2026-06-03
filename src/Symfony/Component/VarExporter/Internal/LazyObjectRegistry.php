<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

/**
 * Stores the state of lazy objects and caches related reflection information.
 *
 * As a micro-optimization, this class uses no type declarations.
 *
 * @internal
 */
class LazyObjectRegistry
{
    public const PROPERTY_HAS_HOOKS = 1;
    public const PROPERTY_NOT_BY_REF = 2;

    /**
     * @var array<class-string, \ReflectionClass>
     */
    public static array $classReflectors = [];

    /**
     * @var array<class-string, array<string, mixed>>
     */
    public static array $defaultProperties = [];

    /**
     * @var array<class-string, list<\Closure>>
     */
    public static array $classResetters = [];

    /**
     * @var array<class-string, array{get: \Closure, set: \Closure, isset: \Closure, unset: \Closure}>
     */
    public static array $classAccessors = [];

    /**
     * @var array<class-string, int>
     */
    public static array $parentGet = [];

    public static array $propertyScopes = [];

    public static function getPropertyScopes($class): array
    {
        $propertyScopes = [];
        $r = new \ReflectionClass($class);

        foreach ($r->getProperties() as $property) {
            $flags = $property->getModifiers();

            if (\ReflectionProperty::IS_STATIC & $flags) {
                continue;
            }
            $name = $property->name;
            $access = ($flags << 2) | ($flags & \ReflectionProperty::IS_READONLY ? self::PROPERTY_NOT_BY_REF : 0);

            if (!$property->isAbstract() && $h = $property->getHooks()) {
                $access |= self::PROPERTY_HAS_HOOKS | (isset($h['get']) && !$h['get']->returnsReference() ? self::PROPERTY_NOT_BY_REF : 0);
            }

            if (\ReflectionProperty::IS_PRIVATE & $flags) {
                $propertyScopes["\0$class\0$name"] = $propertyScopes[$name] = [$class, $name, null, $access, $property];

                continue;
            }

            $propertyScopes[$name] = [$class, $name, null, $access, $property];

            if ($flags & \ReflectionProperty::IS_PRIVATE_SET) {
                $propertyScopes[$name][2] = $property->class;
            }

            if (\ReflectionProperty::IS_PROTECTED & $flags) {
                $propertyScopes["\0*\0$name"] = $propertyScopes[$name];
            }
        }

        while ($r = $r->getParentClass()) {
            $class = $r->name;

            foreach ($r->getProperties(\ReflectionProperty::IS_PRIVATE) as $property) {
                $flags = $property->getModifiers();

                if (\ReflectionProperty::IS_STATIC & $flags) {
                    continue;
                }
                $name = $property->name;
                $access = ($flags << 2) | ($flags & \ReflectionProperty::IS_READONLY ? self::PROPERTY_NOT_BY_REF : 0);

                if ($h = $property->getHooks()) {
                    $access |= self::PROPERTY_HAS_HOOKS | (isset($h['get']) && !$h['get']->returnsReference() ? self::PROPERTY_NOT_BY_REF : 0);
                }

                $propertyScopes["\0$class\0$name"] = [$class, $name, null, $access, $property];
                $propertyScopes[$name] ??= $propertyScopes["\0$class\0$name"];
            }
        }

        return $propertyScopes;
    }

    public static function getClassResetters($class)
    {
        $classProperties = [];
        $hookedProperties = [];

        if ((self::$classReflectors[$class] ??= new \ReflectionClass($class))->isInternal()) {
            $propertyScopes = [];
        } else {
            $propertyScopes = self::$propertyScopes[$class] ??= self::getPropertyScopes($class);
        }

        foreach ($propertyScopes as $key => [$scope, $name, $writeScope, $access]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;

            if ($k !== $key || "\0$class\0lazyObjectState" === $k) {
                continue;
            }

            if ($access & self::PROPERTY_HAS_HOOKS) {
                $hookedProperties[$k] = true;
            } else {
                $classProperties[$writeScope ?? $scope][$name] = $key;
            }
        }

        $resetters = [];
        foreach ($classProperties as $scope => $properties) {
            $resetters[] = \Closure::bind(static function ($instance, $skippedProperties) use ($properties) {
                foreach ($properties as $name => $key) {
                    if (!\array_key_exists($key, $skippedProperties)) {
                        unset($instance->$name);
                    }
                }
            }, null, $scope);
        }

        return $resetters;
    }

    public static function getParentGet($class): int
    {
        $parent = get_parent_class($class);

        if (!$parent || !method_exists($parent, '__get')) {
            return 0;
        }

        $m = new \ReflectionMethod($parent, '__get');

        return !$m->isAbstract() && !$m->isPrivate() ? ($m->returnsReference() ? 2 : 1) : 0;
    }
}
