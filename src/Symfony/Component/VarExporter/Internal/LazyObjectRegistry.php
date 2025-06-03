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
     * @var array<class-string, array{set: bool, isset: bool, unset: bool, clone: bool, serialize: bool, unserialize: bool, sleep: bool, wakeup: bool, destruct: bool, get: int}>
     */
    public static array $parentMethods = [];

    public static ?\Closure $noInitializerState = null;

    public static function getClassResetters($class)
    {
        $classProperties = [];

        if ((self::$classReflectors[$class] ??= new \ReflectionClass($class))->isInternal()) {
            $propertyScopes = [];
        } else {
            $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);
        }

        foreach ($propertyScopes as $key => [$scope, $name, $readonlyScope]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;

            if ($k === $key && "\0$class\0lazyObjectState" !== $k) {
                $classProperties[$readonlyScope ?? $scope][$name] = $key;
            }
        }

        $resetters = [];
        foreach ($classProperties as $scope => $properties) {
            $resetters[] = \Closure::bind(static function ($instance, $skippedProperties, $onlyProperties = null) use ($properties) {
                foreach ($properties as $name => $key) {
                    if (!\array_key_exists($key, $skippedProperties) && (null === $onlyProperties || \array_key_exists($key, $onlyProperties))) {
                        unset($instance->$name);
                    }
                }
            }, null, $scope);
        }

        $resetters[] = static function ($instance, $skippedProperties, $onlyProperties = null) {
            foreach ((array) $instance as $name => $value) {
                if ("\0" !== ($name[0] ?? '') && !\array_key_exists($name, $skippedProperties) && (null === $onlyProperties || \array_key_exists($name, $onlyProperties))) {
                    unset($instance->$name);
                }
            }
        };

        return $resetters;
    }

    public static function getClassAccessors($class)
    {
        return \Closure::bind(static fn () => [
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
            'isset' => static fn ($instance, $name) => isset($instance->$name),
            'unset' => static function ($instance, $name) {
                unset($instance->$name);
            },
        ], null, \Closure::class === $class ? null : $class)();
    }

    public static function getParentMethods($class)
    {
        $parent = get_parent_class($class);
        $methods = [];

        foreach (['set', 'isset', 'unset', 'clone', 'serialize', 'unserialize', 'sleep', 'wakeup', 'destruct', 'get'] as $method) {
            if (!$parent || !method_exists($parent, '__'.$method)) {
                $methods[$method] = false;
            } else {
                $m = new \ReflectionMethod($parent, '__'.$method);
                $methods[$method] = !$m->isAbstract() && !$m->isPrivate();
            }
        }

        $methods['get'] = $methods['get'] ? ($m->returnsReference() ? 2 : 1) : 0;

        return $methods;
    }

    public static function getScope($propertyScopes, $class, $property, $readonlyScope = null)
    {
        if (null === $readonlyScope && !isset($propertyScopes[$k = "\0$class\0$property"]) && !isset($propertyScopes[$k = "\0*\0$property"])) {
            return null;
        }
        $frame = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2];

        if (\ReflectionProperty::class === $scope = $frame['class'] ?? \Closure::class) {
            $scope = $frame['object']->class;
        }
        if (null === $readonlyScope && '*' === $k[1] && ($class === $scope || (is_subclass_of($class, $scope) && !isset($propertyScopes["\0$scope\0$property"])))) {
            return null;
        }

        return $scope;
    }
}
