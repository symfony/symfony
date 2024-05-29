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

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\VarExporter\Hydrator as PublicHydrator;
use Symfony\Component\VarExporter\Internal\Hydrator;
use Symfony\Component\VarExporter\Internal\LazyObjectRegistry as Registry;
use Symfony\Component\VarExporter\Internal\LazyObjectState;
use Symfony\Component\VarExporter\Internal\LazyObjectTrait;

trait LazyProxyTrait
{
    use LazyObjectTrait;

    /**
     * Creates a lazy-loading virtual proxy.
     *
     * @param \Closure():object $initializer Returns the proxied object
     * @param static|null       $instance
     */
    public static function createLazyProxy(\Closure $initializer, ?object $instance = null): static
    {
        if (self::class !== $class = $instance ? $instance::class : static::class) {
            $skippedProperties = ["\0".self::class."\0lazyObjectState" => true];
        }

        if (!isset(Registry::$defaultProperties[$class])) {
            Registry::$classReflectors[$class] ??= new \ReflectionClass($class);
            $instance ??= Registry::$classReflectors[$class]->newInstanceWithoutConstructor();
            Registry::$defaultProperties[$class] ??= (array) $instance;
            Registry::$classResetters[$class] ??= Registry::getClassResetters($class);

            if (self::class === $class && \defined($class.'::LAZY_OBJECT_PROPERTY_SCOPES')) {
                Hydrator::$propertyScopes[$class] ??= $class::LAZY_OBJECT_PROPERTY_SCOPES;
            }
        } else {
            $instance ??= Registry::$classReflectors[$class]->newInstanceWithoutConstructor();
        }

        $instance->lazyObjectState = new LazyObjectState($initializer);

        foreach (Registry::$classResetters[$class] as $reset) {
            $reset($instance, $skippedProperties ??= []);
        }

        return $instance;
    }

    /**
     * Returns whether the object is initialized.
     *
     * @param $partial Whether partially initialized objects should be considered as initialized
     */
    #[Ignore]
    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        return !isset($this->lazyObjectState) || isset($this->lazyObjectState->realInstance) || Registry::$noInitializerState === $this->lazyObjectState->initializer;
    }

    /**
     * Forces initialization of a lazy object and returns it.
     */
    public function initializeLazyObject(): parent
    {
        if ($state = $this->lazyObjectState ?? null) {
            return $state->realInstance ??= ($state->initializer)();
        }

        return $this;
    }

    /**
     * @return bool Returns false when the object cannot be reset, ie when it's not a lazy object
     */
    public function resetLazyObject(): bool
    {
        if (!isset($this->lazyObjectState) || Registry::$noInitializerState === $this->lazyObjectState->initializer) {
            return false;
        }

        unset($this->lazyObjectState->realInstance);

        return true;
    }

    public function &__get($name): mixed
    {
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;
        $instance = $this;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name);

            if (null === $scope || isset($propertyScopes["\0$scope\0$name"])) {
                if ($state = $this->lazyObjectState ?? null) {
                    $instance = $state->realInstance ??= ($state->initializer)();
                }
                $parent = 2;
                goto get_in_scope;
            }
        }
        $parent = (Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['get'];

        if ($state = $this->lazyObjectState ?? null) {
            $instance = $state->realInstance ??= ($state->initializer)();
        } else {
            if (2 === $parent) {
                return parent::__get($name);
            }
            $value = parent::__get($name);

            return $value;
        }

        if (!$parent && null === $class && !\array_key_exists($name, (array) $instance)) {
            $frame = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            trigger_error(sprintf('Undefined property: %s::$%s in %s on line %s', $instance::class, $name, $frame['file'], $frame['line']), \E_USER_NOTICE);
        }

        get_in_scope:

        try {
            if (null === $scope) {
                if (null === $readonlyScope && 1 !== $parent) {
                    return $instance->$name;
                }
                $value = $instance->$name;

                return $value;
            }
            $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);

            return $accessor['get']($instance, $name, null !== $readonlyScope || 1 === $parent);
        } catch (\Error $e) {
            if (\Error::class !== $e::class || !str_starts_with($e->getMessage(), 'Cannot access uninitialized non-nullable property')) {
                throw $e;
            }

            try {
                if (null === $scope) {
                    $instance->$name = [];

                    return $instance->$name;
                }

                $accessor['set']($instance, $name, []);

                return $accessor['get']($instance, $name, null !== $readonlyScope || 1 === $parent);
            } catch (\Error) {
                throw $e;
            }
        }
    }

    public function __set($name, $value): void
    {
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;
        $instance = $this;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name, $readonlyScope);

            if ($readonlyScope === $scope || isset($propertyScopes["\0$scope\0$name"])) {
                if ($state = $this->lazyObjectState ?? null) {
                    $instance = $state->realInstance ??= ($state->initializer)();
                }
                goto set_in_scope;
            }
        }

        if ($state = $this->lazyObjectState ?? null) {
            $instance = $state->realInstance ??= ($state->initializer)();
        } elseif ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['set']) {
            parent::__set($name, $value);

            return;
        }

        set_in_scope:

        if (null === $scope) {
            $instance->$name = $value;
        } else {
            $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);
            $accessor['set']($instance, $name, $value);
        }
    }

    public function __isset($name): bool
    {
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;
        $instance = $this;

        if ([$class] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name);

            if (null === $scope || isset($propertyScopes["\0$scope\0$name"])) {
                if ($state = $this->lazyObjectState ?? null) {
                    $instance = $state->realInstance ??= ($state->initializer)();
                }
                goto isset_in_scope;
            }
        }

        if ($state = $this->lazyObjectState ?? null) {
            $instance = $state->realInstance ??= ($state->initializer)();
        } elseif ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['isset']) {
            return parent::__isset($name);
        }

        isset_in_scope:

        if (null === $scope) {
            return isset($instance->$name);
        }
        $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);

        return $accessor['isset']($instance, $name);
    }

    public function __unset($name): void
    {
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;
        $instance = $this;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name, $readonlyScope);

            if ($readonlyScope === $scope || isset($propertyScopes["\0$scope\0$name"])) {
                if ($state = $this->lazyObjectState ?? null) {
                    $instance = $state->realInstance ??= ($state->initializer)();
                }
                goto unset_in_scope;
            }
        }

        if ($state = $this->lazyObjectState ?? null) {
            $instance = $state->realInstance ??= ($state->initializer)();
        } elseif ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['unset']) {
            parent::__unset($name);

            return;
        }

        unset_in_scope:

        if (null === $scope) {
            unset($instance->$name);
        } else {
            $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);
            $accessor['unset']($instance, $name);
        }
    }

    public function __clone(): void
    {
        if (!isset($this->lazyObjectState)) {
            if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['clone']) {
                parent::__clone();
            }

            return;
        }

        $this->lazyObjectState = clone $this->lazyObjectState;

        if (isset($this->lazyObjectState->realInstance)) {
            $this->lazyObjectState->realInstance = clone $this->lazyObjectState->realInstance;
        }
    }

    public function __serialize(): array
    {
        $class = self::class;
        $state = $this->lazyObjectState ?? null;

        if (!$state && (Registry::$parentMethods[$class] ??= Registry::getParentMethods($class))['serialize']) {
            $properties = parent::__serialize();
        } else {
            $properties = (array) $this;

            if ($state) {
                unset($properties["\0$class\0lazyObjectState"]);
                $properties["\0$class\0lazyObjectReal"] = $state->realInstance ??= ($state->initializer)();
            }
        }

        if ($state || Registry::$parentMethods[$class]['serialize'] || !Registry::$parentMethods[$class]['sleep']) {
            return $properties;
        }

        $scope = get_parent_class($class);
        $data = [];

        foreach (parent::__sleep() as $name) {
            $value = $properties[$k = $name] ?? $properties[$k = "\0*\0$name"] ?? $properties[$k = "\0$class\0$name"] ?? $properties[$k = "\0$scope\0$name"] ?? $k = null;

            if (null === $k) {
                trigger_error(sprintf('serialize(): "%s" returned as member variable from __sleep() but does not exist', $name), \E_USER_NOTICE);
            } else {
                $data[$k] = $value;
            }
        }

        return $data;
    }

    public function __unserialize(array $data): void
    {
        $class = self::class;

        if ($instance = $data["\0$class\0lazyObjectReal"] ?? null) {
            unset($data["\0$class\0lazyObjectReal"]);

            foreach (Registry::$classResetters[$class] ??= Registry::getClassResetters($class) as $reset) {
                $reset($this, $data);
            }

            if ($data) {
                PublicHydrator::hydrate($this, $data);
            }
            $this->lazyObjectState = new LazyObjectState(Registry::$noInitializerState ??= static fn () => throw new \LogicException('Lazy proxy has no initializer.'));
            $this->lazyObjectState->realInstance = $instance;
        } elseif ((Registry::$parentMethods[$class] ??= Registry::getParentMethods($class))['unserialize']) {
            parent::__unserialize($data);
        } else {
            PublicHydrator::hydrate($this, $data);

            if (Registry::$parentMethods[$class]['wakeup']) {
                parent::__wakeup();
            }
        }
    }

    public function __destruct()
    {
        if (isset($this->lazyObjectState)) {
            return;
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['destruct']) {
            parent::__destruct();
        }
    }
}
