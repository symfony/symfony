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

use Symfony\Component\VarExporter\Hydrator as PublicHydrator;

/**
 * Keeps the state of lazy objects.
 *
 * As a micro-optimization, this class uses no type declarations.
 *
 * @internal
 */
class LazyObjectState
{
    public const STATUS_UNINITIALIZED_FULL = 1;
    public const STATUS_UNINITIALIZED_PARTIAL = 2;
    public const STATUS_INITIALIZED_FULL = 3;
    public const STATUS_INITIALIZED_PARTIAL = 4;

    /**
     * @var array<string, true>
     */
    public readonly array $skippedProperties;

    /**
     * @var self::STATUS_*
     */
    public int $status = 0;

    public object $realInstance;

    public function __construct(public readonly \Closure|array $initializer, $skippedProperties = [])
    {
        $this->skippedProperties = $skippedProperties;
        $this->status = \is_array($initializer) ? self::STATUS_UNINITIALIZED_PARTIAL : self::STATUS_UNINITIALIZED_FULL;
    }

    public function initialize($instance, $propertyName, $writeScope)
    {
        if (self::STATUS_INITIALIZED_FULL === $this->status) {
            return self::STATUS_INITIALIZED_FULL;
        }

        if (\is_array($this->initializer)) {
            $class = $instance::class;
            $writeScope ??= $class;
            $propertyScopes = Hydrator::$propertyScopes[$class];
            $propertyScopes[$k = "\0$writeScope\0$propertyName"] ?? $propertyScopes[$k = "\0*\0$propertyName"] ?? $k = $propertyName;

            if ($initializer = $this->initializer[$k] ?? null) {
                $value = $initializer(...[$instance, $propertyName, $writeScope, LazyObjectRegistry::$defaultProperties[$class][$k] ?? null]);
                $accessor = LazyObjectRegistry::$classAccessors[$writeScope] ??= LazyObjectRegistry::getClassAccessors($writeScope);
                $accessor['set']($instance, $propertyName, $value);

                return $this->status = self::STATUS_INITIALIZED_PARTIAL;
            }

            if ($initializer = $this->initializer["\0"] ?? null) {
                if (!\is_array($values = $initializer($instance, LazyObjectRegistry::$defaultProperties[$class]))) {
                    throw new \TypeError(sprintf('The lazy-initializer defined for instance of "%s" must return an array, got "%s".', $class, get_debug_type($values)));
                }
                $properties = (array) $instance;
                foreach ($values as $key => $value) {
                    if (!\array_key_exists($key, $properties) && [$scope, $name, $writeScope] = $propertyScopes[$key] ?? null) {
                        $scope = $writeScope ?? $scope;
                        $accessor = LazyObjectRegistry::$classAccessors[$scope] ??= LazyObjectRegistry::getClassAccessors($scope);
                        $accessor['set']($instance, $name, $value);

                        if ($k === $key) {
                            $this->status = self::STATUS_INITIALIZED_PARTIAL;
                        }
                    }
                }
            }

            return $this->status;
        }

        if (self::STATUS_INITIALIZED_PARTIAL === $this->status) {
            return self::STATUS_INITIALIZED_PARTIAL;
        }

        $this->status = self::STATUS_INITIALIZED_PARTIAL;

        try {
            if ($defaultProperties = array_diff_key(LazyObjectRegistry::$defaultProperties[$instance::class], $this->skippedProperties)) {
                PublicHydrator::hydrate($instance, $defaultProperties);
            }

            ($this->initializer)($instance);
        } catch (\Throwable $e) {
            $this->status = self::STATUS_UNINITIALIZED_FULL;
            $this->reset($instance);

            throw $e;
        }

        return $this->status = self::STATUS_INITIALIZED_FULL;
    }

    public function reset($instance): void
    {
        $class = $instance::class;
        $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);
        $skippedProperties = $this->skippedProperties;
        $properties = (array) $instance;
        $onlyProperties = \is_array($this->initializer) ? $this->initializer : null;

        foreach ($propertyScopes as $key => [$scope, $name, , $access]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;

            if ($k === $key && ($access & Hydrator::PROPERTY_HAS_HOOKS || ($access >> 2) & \ReflectionProperty::IS_READONLY || !\array_key_exists($k, $properties))) {
                $skippedProperties[$k] = true;
            }
        }

        foreach (LazyObjectRegistry::$classResetters[$class] as $reset) {
            $reset($instance, $skippedProperties, $onlyProperties);
        }

        $this->status = self::STATUS_INITIALIZED_FULL === $this->status ? self::STATUS_UNINITIALIZED_FULL : self::STATUS_UNINITIALIZED_PARTIAL;
    }
}
