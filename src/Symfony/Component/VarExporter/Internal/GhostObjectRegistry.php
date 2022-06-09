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
 * Stores the state of lazy ghost objects and caches related reflection information.
 *
 * As a micro-optimization, this class uses no type declarations.
 *
 * @internal
 */
class GhostObjectRegistry
{
    /**
     * @var array<int, GhostObjectState>
     */
    public static $states = [];

    /**
     * @var array<class-string, \ReflectionClass>
     */
    public static $classReflectors = [];

    /**
     * @var array<class-string, array<string, mixed>>
     */
    public static $defaultProperties = [];

    /**
     * @var array<class-string, list<\Closure>>
     */
    public static $classResetters = [];

    /**
     * @var array<class-string, array{get: \Closure, set: \Closure, isset: \Closure, unset: \Closure}>
     */
    public static $classAccessors = [];

    /**
     * @var array<class-string, array{get: int, set: bool, isset: bool, unset: bool, clone: bool, serialize: bool, sleep: bool, destruct: bool}>
     */
    public static $parentMethods = [];

    public static function getClassResetters($class)
    {
        $classProperties = [];
        $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);

        foreach ($propertyScopes as $key => [$scope, $name, $readonlyScope]) {
            if ('lazyGhostObjectId' !== $name && null !== ($propertyScopes["\0$scope\0$name"] ?? $propertyScopes["\0*\0$name"] ?? $readonlyScope)) {
                $classProperties[$readonlyScope ?? $scope][$name] = $key;
            }
        }

        $resetters = [];
        foreach ($classProperties as $scope => $properties) {
            $resetters[] = \Closure::bind(static function ($instance, $skippedProperties = []) use ($properties) {
                foreach ($properties as $name => $key) {
                    if (!\array_key_exists($key, $skippedProperties)) {
                        unset($instance->$name);
                    }
                }
            }, null, $scope);
        }

        $resetters[] = static function ($instance, $skippedProperties = []) {
            foreach ((array) $instance as $name => $value) {
                if ("\0" !== ($name[0] ?? '') && !\array_key_exists($name, $skippedProperties)) {
                    unset($instance->$name);
                }
            }
        };

        return $resetters;
    }

    public static function getClassAccessors($class)
    {
        return \Closure::bind(static function () {
            return [
                'get' => static function &($instance, $name, $readonly) {
                    if (!$readonly) {
                        return $instance->$name;
                    }
                    $value = $instance->$name;

                    return $value;
                },
                'set' => static function ($instance, $name, $value) {
                    $instance->$name = $value;
                },
                'isset' => static function ($instance, $name) {
                    return isset($instance->$name);
                },
                'unset' => static function ($instance, $name) {
                    unset($instance->$name);
                },
            ];
        }, null, $class)();
    }

    public static function getParentMethods($class)
    {
        $parent = get_parent_class($class);

        return [
            'get' => $parent && method_exists($parent, '__get') ? ((new \ReflectionMethod($parent, '__get'))->returnsReference() ? 2 : 1) : 0,
            'set' => $parent && method_exists($parent, '__set'),
            'isset' => $parent && method_exists($parent, '__isset'),
            'unset' => $parent && method_exists($parent, '__unset'),
            'clone' => $parent && method_exists($parent, '__clone'),
            'serialize' => $parent && method_exists($parent, '__serialize'),
            'sleep' => $parent && method_exists($parent, '__sleep'),
            'destruct' => $parent && method_exists($parent, '__destruct'),
        ];
    }
}
