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

use Symfony\Component\VarExporter\Hydrator;

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
     * @var array<class-string|'*', array<string, true>>
     */
    public array $preInitUnsetProperties;

    /**
     * @var array<string, true>
     */
    public array $preInitSetProperties;

    /**
     * @var array<class-string|'*', array<string, true>>
     */
    public array $unsetProperties;

    /**
     * @var array<string, true>
     */
    public array $skippedProperties;

    /**
     * @var self::STATUS_*
     */
    public int $status = 0;

    public function __construct(public \Closure $initializer, $skippedProperties = [])
    {
        $this->skippedProperties = $this->preInitSetProperties = $skippedProperties;
    }

    /**
     * @return bool Returns true when fully-initializing, false when partial-initializing
     */
    public function initialize($instance, $propertyName, $propertyScope)
    {
        if (!$this->status) {
            $this->status = 1 < (new \ReflectionFunction($this->initializer))->getNumberOfRequiredParameters() ? self::STATUS_INITIALIZED_PARTIAL : self::STATUS_UNINITIALIZED_FULL;
            $this->preInitUnsetProperties = $this->unsetProperties ??= [];

            if (\count($this->preInitSetProperties) !== \count($properties = $this->preInitSetProperties + (array) $instance)) {
                $this->preInitSetProperties = array_fill_keys(array_keys($properties), true);
            }

            if (null === $propertyName) {
                return self::STATUS_INITIALIZED_PARTIAL !== $this->status;
            }
        }

        if (self::STATUS_INITIALIZED_FULL === $this->status) {
            return true;
        }

        if (self::STATUS_UNINITIALIZED_FULL === $this->status) {
            if ($defaultProperties = array_diff_key(LazyObjectRegistry::$defaultProperties[$instance::class], $this->preInitSetProperties)) {
                Hydrator::hydrate($instance, $defaultProperties);
            }

            $this->status = self::STATUS_INITIALIZED_FULL;
            ($this->initializer)($instance);

            return true;
        }

        $value = ($this->initializer)(...[$instance, $propertyName, $propertyScope]);

        $propertyScope ??= $instance::class;
        $accessor = LazyObjectRegistry::$classAccessors[$propertyScope] ??= LazyObjectRegistry::getClassAccessors($propertyScope);

        $accessor['set']($instance, $propertyName, $value);

        return false;
    }
}
