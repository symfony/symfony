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
     * @var self::STATUS_*
     */
    public int $status = self::STATUS_UNINITIALIZED_FULL;

    public object $realInstance;
    public object $cloneInstance;

    /**
     * @param array<string, true> $skippedProperties
     */
    public function __construct(
        public ?\Closure $initializer = null,
        public array $skippedProperties = [],
    ) {
    }

    public function initialize($instance, $propertyName, $writeScope)
    {
        if (self::STATUS_UNINITIALIZED_FULL !== $this->status) {
            return $this->status;
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

        foreach ($propertyScopes as $key => [$scope, $name, , $access]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;

            if ($k === $key && ($access & Hydrator::PROPERTY_HAS_HOOKS || ($access >> 2) & \ReflectionProperty::IS_READONLY || !\array_key_exists($k, $properties))) {
                $skippedProperties[$k] = true;
            }
        }

        foreach (LazyObjectRegistry::$classResetters[$class] as $reset) {
            $reset($instance, $skippedProperties);
        }

        foreach ((array) $instance as $name => $value) {
            if ("\0" !== ($name[0] ?? '') && !\array_key_exists($name, $skippedProperties)) {
                unset($instance->$name);
            }
        }

        $this->status = self::STATUS_UNINITIALIZED_FULL;
    }

    public function __clone()
    {
        if (isset($this->cloneInstance)) {
            try {
                $this->realInstance = $this->cloneInstance;
            } finally {
                unset($this->cloneInstance);
            }
        } elseif (isset($this->realInstance)) {
            $this->realInstance = clone $this->realInstance;
        }
    }

    public function __get($name)
    {
        if ('realInstance' !== $name) {
            throw new \BadMethodCallException(\sprintf('No such property "%s::$%s"', self::class, $name));
        }

        return $this->realInstance = ($this->initializer)();
    }
}
