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
    public const STATUS_INITIALIZED_PARTIAL = 1;
    public const STATUS_UNINITIALIZED_FULL = 2;
    public const STATUS_INITIALIZED_FULL = 3;

    /**
     * @var array<string, true>
     */
    public readonly array $skippedProperties;

    /**
     * @var self::STATUS_*
     */
    public int $status = 0;

    public function __construct(public \Closure $initializer, $skippedProperties = [])
    {
        $this->skippedProperties = $skippedProperties;
    }

    public function initialize($instance, $propertyName, $propertyScope)
    {
        if (!$this->status) {
            $this->status = 4 <= (new \ReflectionFunction($this->initializer))->getNumberOfParameters() ? self::STATUS_INITIALIZED_PARTIAL : self::STATUS_UNINITIALIZED_FULL;

            if (null === $propertyName) {
                return $this->status;
            }
        }

        if (self::STATUS_INITIALIZED_FULL === $this->status) {
            return self::STATUS_INITIALIZED_FULL;
        }

        if (self::STATUS_INITIALIZED_PARTIAL === $this->status) {
            $class = $instance::class;
            $propertyScope ??= $class;
            $propertyScopes = Hydrator::$propertyScopes[$class];
            $propertyScopes[$k = "\0$propertyScope\0$propertyName"] ?? $propertyScopes[$k = "\0*\0$propertyName"] ?? $k = $propertyName;

            $value = ($this->initializer)(...[$instance, $propertyName, $propertyScope, LazyObjectRegistry::$defaultProperties[$class][$k] ?? null]);

            $accessor = LazyObjectRegistry::$classAccessors[$propertyScope] ??= LazyObjectRegistry::getClassAccessors($propertyScope);
            $accessor['set']($instance, $propertyName, $value);

            return self::STATUS_INITIALIZED_PARTIAL;
        }

        $this->status = self::STATUS_INITIALIZED_FULL;

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

        return self::STATUS_INITIALIZED_FULL;
    }

    public function reset($instance): void
    {
        $class = $instance::class;
        $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);
        $skippedProperties = $this->skippedProperties;
        $properties = (array) $instance;

        foreach ($propertyScopes as $key => [$scope, $name, $readonlyScope]) {
            $propertyScopes[$k = "\0$scope\0$name"] ?? $propertyScopes[$k = "\0*\0$name"] ?? $k = $name;

            if ($k === $key && (null !== $readonlyScope || !\array_key_exists($k, $properties))) {
                $skippedProperties[$k] = true;
            }
        }

        foreach (LazyObjectRegistry::$classResetters[$class] as $reset) {
            $reset($instance, $skippedProperties);
        }
    }
}
