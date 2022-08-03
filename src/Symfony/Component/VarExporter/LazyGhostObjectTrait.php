<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter;

use Symfony\Component\VarExporter\Internal\EmptyScope;
use Symfony\Component\VarExporter\Internal\GhostObjectId;
use Symfony\Component\VarExporter\Internal\GhostObjectRegistry as Registry;
use Symfony\Component\VarExporter\Internal\GhostObjectState;
use Symfony\Component\VarExporter\Internal\Hydrator;

trait LazyGhostObjectTrait
{
    private ?GhostObjectId $lazyGhostObjectId = null;

    /**
     * @param \Closure(static):void|\Closure(static, string, ?string):mixed $initializer Initializes the instance passed as argument; when partial initialization
     *                                                                                   is desired the closure should take extra arguments $propertyName and
     *                                                                                   $propertyScope and should return the value of the corresponding property
     */
    public static function createLazyGhostObject(\Closure $initializer): static
    {
        $class = static::class;
        $instance = (Registry::$classReflectors[$class] ??= new \ReflectionClass($class))->newInstanceWithoutConstructor();

        Registry::$defaultProperties[$class] ??= (array) $instance;
        $instance->lazyGhostObjectId = new GhostObjectId();
        $state = Registry::$states[$instance->lazyGhostObjectId->id] = new GhostObjectState();
        $state->initializer = $initializer;

        foreach (Registry::$classResetters[$class] ??= Registry::getClassResetters($class) as $reset) {
            $reset($instance);
        }

        return $instance;
    }

    /**
     * Forces initialization of a lazy ghost object.
     */
    public function initializeLazyGhostObject(): void
    {
        if (!$state = Registry::$states[$this->lazyGhostObjectId?->id] ?? null) {
            return;
        }

        $class = static::class;
        $properties = (array) $this;
        $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);
        foreach ($propertyScopes as $key => [$scope, $name, $readonlyScope]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0".($scope = '*')."\0$name"] ?? $k = $name;

            if ($k !== $key || \array_key_exists($k, $properties) || isset($state->unsetProperties[$scope][$name])) {
                continue;
            }
            if ($state->initialize($this, $name, $readonlyScope ?? ('*' !== $scope ? $scope : null))) {
                return;
            }
            $properties = (array) $this;
        }
    }

    /**
     * @return bool Returns false when the object cannot be reset, ie when it's not a ghost object
     */
    public function resetLazyGhostObject(): bool
    {
        if (!$state = Registry::$states[$this->lazyGhostObjectId?->id] ?? null) {
            return false;
        }

        if (!$state->status) {
            $state->preInitSetProperties = [];
            $state->preInitUnsetProperties ??= $state->unsetProperties ?? [];
        }

        $class = static::class;
        $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);
        $skippedProperties = $state->preInitSetProperties;
        foreach ($propertyScopes as $key => [$scope, $name, $readonlyScope]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;

            if (null !== $readonlyScope && $k === $key) {
                $skippedProperties[$key] = true;
            }
        }

        foreach (Registry::$classResetters[$class] as $reset) {
            $reset($this, $skippedProperties);
        }

        if (GhostObjectState::STATUS_INITIALIZED_FULL === $state->status) {
            $state->status = GhostObjectState::STATUS_UNINITIALIZED_FULL;
        }

        $state->unsetProperties = $state->preInitUnsetProperties;

        return true;
    }

    public function &__get($name)
    {
        $propertyScopes = Hydrator::$propertyScopes[static::class] ??= Hydrator::getPropertyScopes(static::class);
        $scope = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            if (isset($propertyScopes["\0$class\0$name"]) || isset($propertyScopes["\0*\0$name"])) {
                $scope = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? EmptyScope::class;

                if (isset($propertyScopes["\0*\0$name"]) && ($class === $scope || is_subclass_of($class, $scope))) {
                    $scope = null;
                }
            }

            if ($state = Registry::$states[$this->lazyGhostObjectId?->id] ?? null) {
                if (isset($state->unsetProperties[$scope ?? '*'][$name])) {
                    $class = null;
                } elseif (null === $scope || isset($propertyScopes["\0$scope\0$name"])) {
                    $state->initialize($this, $name, $readonlyScope ?? $scope);
                    goto get_in_scope;
                }
            }
        }

        if ($parent = (Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['get']) {
            if (2 === $parent) {
                $value = &parent::__get($name);
            } else {
                $value = parent::__get($name);
            }

            return $value;
        }

        if (null === $class) {
            $frame = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            trigger_error(sprintf('Undefined property: %s::$%s in %s on line %s', static::class, $name, $frame['file'], $frame['line']), \E_USER_NOTICE);
        }

        get_in_scope:

        if (null === $scope) {
            if (null === $readonlyScope) {
                return $this->$name;
            }
            $value = $this->$name;

            return $value;
        }
        $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);

        return $accessor['get']($this, $name, null !== $readonlyScope);
    }

    public function __set($name, $value): void
    {
        $propertyScopes = Hydrator::$propertyScopes[static::class] ??= Hydrator::getPropertyScopes(static::class);
        $scope = null;
        $state = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            if (null !== $readonlyScope || isset($propertyScopes["\0$class\0$name"]) || isset($propertyScopes["\0*\0$name"])) {
                $scope = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? EmptyScope::class;

                if (null === $readonlyScope && isset($propertyScopes["\0*\0$name"]) && ($class === $scope || is_subclass_of($class, $scope))) {
                    $scope = null;
                }
            }

            $state = Registry::$states[$this->lazyGhostObjectId?->id] ?? null;
            if ($state && ($readonlyScope === $scope || isset($propertyScopes["\0$scope\0$name"]))) {
                if (!$state->status && null === $state->preInitUnsetProperties) {
                    $propertyScopes[$k = "\0$class\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;
                    $state->preInitSetProperties[$k] = true;
                }

                goto set_in_scope;
            }
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['set']) {
            parent::__set($name, $value);

            return;
        }

        set_in_scope:

        if (null === $scope) {
            $this->$name = $value;
            unset($state->unsetProperties['*'][$name]);

            return;
        }
        $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);

        $accessor['set']($this, $name, $value);
        unset($state->unsetProperties[$scope][$name]);
    }

    public function __isset($name): bool
    {
        $propertyScopes = Hydrator::$propertyScopes[static::class] ??= Hydrator::getPropertyScopes(static::class);
        $scope = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            if (isset($propertyScopes["\0$class\0$name"]) || isset($propertyScopes["\0*\0$name"])) {
                $scope = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? EmptyScope::class;

                if (isset($propertyScopes["\0*\0$name"]) && ($class === $scope || is_subclass_of($class, $scope))) {
                    $scope = null;
                }
            }

            if ($state = Registry::$states[$this->lazyGhostObjectId?->id] ?? null) {
                if (isset($state->unsetProperties[$scope ?? '*'][$name])) {
                    return false;
                }

                if (null === $scope || isset($propertyScopes["\0$scope\0$name"])) {
                    $state->initialize($this, $name, $readonlyScope ?? $scope);
                    goto isset_in_scope;
                }
            }
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['isset']) {
            return parent::__isset($name);
        }

        isset_in_scope:

        if (null === $scope) {
            return isset($this->$name);
        }
        $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);

        return $accessor['isset']($this, $name);
    }

    public function __unset($name): void
    {
        $propertyScopes = Hydrator::$propertyScopes[static::class] ??= Hydrator::getPropertyScopes(static::class);
        $scope = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            if (null !== $readonlyScope || isset($propertyScopes["\0$class\0$name"]) || isset($propertyScopes["\0*\0$name"])) {
                $scope = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? EmptyScope::class;

                if (null === $readonlyScope && isset($propertyScopes["\0*\0$name"]) && ($class === $scope || is_subclass_of($class, $scope))) {
                    $scope = null;
                }
            }

            $state = Registry::$states[$this->lazyGhostObjectId?->id] ?? null;
            if ($state && ($readonlyScope === $scope || isset($propertyScopes["\0$scope\0$name"]))) {
                if (!$state->status && null === $state->preInitUnsetProperties) {
                    $propertyScopes[$k = "\0$class\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;
                    unset($state->preInitSetProperties[$k]);
                }
                $state->unsetProperties[$scope ?? '*'][$name] = true;

                return;
            }
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['unset']) {
            parent::__unset($name);

            return;
        }

        if (null === $scope) {
            unset($this->$name);

            return;
        }
        $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);

        $accessor['unset']($this, $name);
    }

    public function __clone()
    {
        if ($previousId = $this->lazyGhostObjectId?->id) {
            $this->lazyGhostObjectId = clone $this->lazyGhostObjectId;
            Registry::$states[$this->lazyGhostObjectId->id] = clone Registry::$states[$previousId];
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['clone']) {
            parent::__clone();
        }
    }

    public function __serialize(): array
    {
        $class = self::class;

        if ((Registry::$parentMethods[$class] ??= Registry::getParentMethods($class))['serialize']) {
            return parent::__serialize();
        }

        $this->initializeLazyGhostObject();
        $properties = (array) $this;
        unset($properties["\0$class\0lazyGhostObjectId"]);

        if (!Registry::$parentMethods[$class]['sleep']) {
            return $properties;
        }

        $scope = get_parent_class($class);
        $data = [];

        foreach (parent::__sleep() as $name) {
            $value = $properties[$k = $name] ?? $properties[$k = "\0*\0$name"] ?? $properties[$k = "\0$scope\0$name"] ?? $k = null;

            if (null === $k) {
                trigger_error(sprintf('serialize(): "%s" returned as member variable from __sleep() but does not exist', $name), \E_USER_NOTICE);
            } else {
                $data[$k] = $value;
            }
        }

        return $data;
    }

    public function __destruct()
    {
        if (!(Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['destruct']) {
            return;
        }

        if ((Registry::$states[$this->lazyGhostObjectId?->id] ?? null)?->status) {
            parent::__destruct();
        }
    }
}
