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

    public static function getClassResetters($class)
    {
        $classProperties = [];
        $hookedProperties = [];

        if ((self::$classReflectors[$class] ??= new \ReflectionClass($class))->isInternal()) {
            $propertyScopes = [];
        } else {
            $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);
        }

        foreach ($propertyScopes as $key => [$scope, $name, $writeScope, $access]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;

            if ($k !== $key || "\0$class\0lazyObjectState" === $k) {
                continue;
            }

            if ($access & Hydrator::PROPERTY_HAS_HOOKS) {
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
