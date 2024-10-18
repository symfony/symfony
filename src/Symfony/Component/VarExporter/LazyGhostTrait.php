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
use Symfony\Component\VarExporter\Internal\Hydrator;
use Symfony\Component\VarExporter\Internal\LazyObjectRegistry as Registry;
use Symfony\Component\VarExporter\Internal\LazyObjectState;
use Symfony\Component\VarExporter\Internal\LazyObjectTrait;

trait LazyGhostTrait
{
    use LazyObjectTrait;

    /**
     * Creates a lazy-loading ghost instance.
     *
     * Skipped properties should be indexed by their array-cast identifier, see
     * https://php.net/manual/language.types.array#language.types.array.casting
     *
     * @param \Closure(static):void    $initializer       The closure should initialize the object it receives as argument
     * @param array<string, true>|null $skippedProperties An array indexed by the properties to skip, a.k.a. the ones
     *                                                    that the initializer doesn't initialize, if any
     * @param static|null              $instance
     */
    public static function createLazyGhost(\Closure $initializer, ?array $skippedProperties = null, ?object $instance = null): static
    {
        if (self::class !== $class = $instance ? $instance::class : static::class) {
            $skippedProperties["\0".self::class."\0lazyObjectState"] = true;
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

        if (isset($instance->lazyObjectState)) {
            $instance->lazyObjectState->initializer = $initializer;
            $instance->lazyObjectState->skippedProperties = $skippedProperties ??= [];

            if (LazyObjectState::STATUS_UNINITIALIZED_FULL !== $instance->lazyObjectState->status) {
                $instance->lazyObjectState->reset($instance);
            }

            return $instance;
        }

        $instance->lazyObjectState = new LazyObjectState($initializer, $skippedProperties ??= []);

        foreach (Registry::$classResetters[$class] as $reset) {
            $reset($instance, $skippedProperties);
        }

        return $instance;
    }

    /**
     * Returns whether the object is initialized.
     *
     * @param bool $partial Whether partially initialized objects should be considered as initialized
     */
    #[Ignore]
    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        if (!$state = $this->lazyObjectState ?? null) {
            return true;
        }

        return LazyObjectState::STATUS_INITIALIZED_FULL === $state->status;
    }

    /**
     * Forces initialization of a lazy object and returns it.
     */
    public function initializeLazyObject(): static
    {
        if (!$state = $this->lazyObjectState ?? null) {
            return $this;
        }

        if (LazyObjectState::STATUS_UNINITIALIZED_FULL === $state->status) {
            $state->initialize($this, '', null);
        }

        return $this;
    }

    /**
     * @return bool Returns false when the object cannot be reset, ie when it's not a lazy object
     */
    public function resetLazyObject(): bool
    {
        if (!$state = $this->lazyObjectState ?? null) {
            return false;
        }

        if (LazyObjectState::STATUS_UNINITIALIZED_FULL !== $state->status) {
            $state->reset($this);
        }

        return true;
    }

    public function &__get($name): mixed
    {
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name);
            $state = $this->lazyObjectState ?? null;

            if ($state && (null === $scope || isset($propertyScopes["\0$scope\0$name"]))) {
                if (LazyObjectState::STATUS_INITIALIZED_FULL === $state->status) {
                    // Work around php/php-src#12695
                    $property = null === $scope ? $name : "\0$scope\0$name";
                    $property = $propertyScopes[$property][3]
                        ?? Hydrator::$propertyScopes[$this::class][$property][3] = new \ReflectionProperty($scope ?? $class, $name);
                } else {
                    $property = null;
                }

                if ($property?->isInitialized($this) ?? LazyObjectState::STATUS_UNINITIALIZED_PARTIAL !== $state->initialize($this, $name, $readonlyScope ?? $scope)) {
                    goto get_in_scope;
                }
            }
        }

        if ($parent = (Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['get']) {
            if (2 === $parent) {
                return parent::__get($name);
            }
            $value = parent::__get($name);

            return $value;
        }

        if (null === $class) {
            $frame = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            trigger_error(\sprintf('Undefined property: %s::$%s in %s on line %s', $this::class, $name, $frame['file'], $frame['line']), \E_USER_NOTICE);
        }

        get_in_scope:

        try {
            if (null === $scope) {
                if (null === $readonlyScope) {
                    return $this->$name;
                }
                $value = $this->$name;

                return $value;
            }
            $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);

            return $accessor['get']($this, $name, null !== $readonlyScope);
        } catch (\Error $e) {
            if (\Error::class !== $e::class || !str_starts_with($e->getMessage(), 'Cannot access uninitialized non-nullable property')) {
                throw $e;
            }

            try {
                if (null === $scope) {
                    $this->$name = [];

                    return $this->$name;
                }

                $accessor['set']($this, $name, []);

                return $accessor['get']($this, $name, null !== $readonlyScope);
            } catch (\Error) {
                if (preg_match('/^Cannot access uninitialized non-nullable property ([^ ]++) by reference$/', $e->getMessage(), $matches)) {
                    throw new \Error('Typed property '.$matches[1].' must not be accessed before initialization', $e->getCode(), $e->getPrevious());
                }

                throw $e;
            }
        }
    }

    public function __set($name, $value): void
    {
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name, $readonlyScope);
            $state = $this->lazyObjectState ?? null;

            if ($state && ($readonlyScope === $scope || isset($propertyScopes["\0$scope\0$name"]))
                && LazyObjectState::STATUS_INITIALIZED_FULL !== $state->status
            ) {
                if (LazyObjectState::STATUS_UNINITIALIZED_FULL === $state->status) {
                    $state->initialize($this, $name, $readonlyScope ?? $scope);
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
        } else {
            $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);
            $accessor['set']($this, $name, $value);
        }
    }

    public function __isset($name): bool
    {
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name);
            $state = $this->lazyObjectState ?? null;

            if ($state && (null === $scope || isset($propertyScopes["\0$scope\0$name"]))
                && LazyObjectState::STATUS_INITIALIZED_FULL !== $state->status
                && LazyObjectState::STATUS_UNINITIALIZED_PARTIAL !== $state->initialize($this, $name, $readonlyScope ?? $scope)
            ) {
                goto isset_in_scope;
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
        $propertyScopes = Hydrator::$propertyScopes[$this::class] ??= Hydrator::getPropertyScopes($this::class);
        $scope = null;

        if ([$class, , $readonlyScope] = $propertyScopes[$name] ?? null) {
            $scope = Registry::getScope($propertyScopes, $class, $name, $readonlyScope);
            $state = $this->lazyObjectState ?? null;

            if ($state && ($readonlyScope === $scope || isset($propertyScopes["\0$scope\0$name"]))
                && LazyObjectState::STATUS_INITIALIZED_FULL !== $state->status
            ) {
                if (LazyObjectState::STATUS_UNINITIALIZED_FULL === $state->status) {
                    $state->initialize($this, $name, $readonlyScope ?? $scope);
                }
                goto unset_in_scope;
            }
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['unset']) {
            parent::__unset($name);

            return;
        }

        unset_in_scope:

        if (null === $scope) {
            unset($this->$name);
        } else {
            $accessor = Registry::$classAccessors[$scope] ??= Registry::getClassAccessors($scope);
            $accessor['unset']($this, $name);
        }
    }

    public function __clone(): void
    {
        if ($state = $this->lazyObjectState ?? null) {
            $this->lazyObjectState = clone $state;
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['clone']) {
            parent::__clone();
        }
    }

    public function __serialize(): array
    {
        $class = self::class;

        if ((Registry::$parentMethods[$class] ??= Registry::getParentMethods($class))['serialize']) {
            $properties = parent::__serialize();
        } else {
            $this->initializeLazyObject();
            $properties = (array) $this;
        }
        unset($properties["\0$class\0lazyObjectState"]);

        if (Registry::$parentMethods[$class]['serialize'] || !Registry::$parentMethods[$class]['sleep']) {
            return $properties;
        }

        $scope = get_parent_class($class);
        $data = [];

        foreach (parent::__sleep() as $name) {
            $value = $properties[$k = $name] ?? $properties[$k = "\0*\0$name"] ?? $properties[$k = "\0$class\0$name"] ?? $properties[$k = "\0$scope\0$name"] ?? $k = null;

            if (null === $k) {
                trigger_error(\sprintf('serialize(): "%s" returned as member variable from __sleep() but does not exist', $name), \E_USER_NOTICE);
            } else {
                $data[$k] = $value;
            }
        }

        return $data;
    }

    public function __destruct()
    {
        $state = $this->lazyObjectState ?? null;

        if (LazyObjectState::STATUS_UNINITIALIZED_FULL === $state?->status) {
            return;
        }

        if ((Registry::$parentMethods[self::class] ??= Registry::getParentMethods(self::class))['destruct']) {
            parent::__destruct();
        }
    }

    #[Ignore]
    private function setLazyObjectAsInitialized(bool $initialized): void
    {
        if ($state = $this->lazyObjectState ?? null) {
            $state->status = $initialized ? LazyObjectState::STATUS_INITIALIZED_FULL : LazyObjectState::STATUS_UNINITIALIZED_FULL;
        }
    }
}
