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
 * Keeps the state of lazy ghost objects.
 *
 * As a micro-optimization, this class uses no type declarations.
 *
 * @internal
 */
class GhostObjectState
{
    public const STATUS_INITIALIZED_PARTIAL = 1;
    public const STATUS_UNINITIALIZED_FULL = 2;
    public const STATUS_INITIALIZED_FULL = 3;

    public \Closure $initializer;

    /**
     * @var array<class-string|'*', array<string, true>>
     */
    public $preInitUnsetProperties;

    /**
     * @var array<string, true>
     */
    public $preInitSetProperties = [];

    /**
     * @var array<class-string|'*', array<string, true>>
     */
    public $unsetProperties = [];

    /**
     * One of self::STATUS_*.
     *
     * @var int
     */
    public $status;

    /**
     * @return bool Returns true when fully-initializing, false when partial-initializing
     */
    public function initialize($instance, $propertyName, $propertyScope)
    {
        if (!$this->status) {
            $this->status = 1 < (new \ReflectionFunction($this->initializer))->getNumberOfRequiredParameters() ? self::STATUS_INITIALIZED_PARTIAL : self::STATUS_UNINITIALIZED_FULL;
            $this->preInitUnsetProperties ??= $this->unsetProperties;
        }

        if (self::STATUS_INITIALIZED_FULL === $this->status) {
            return true;
        }

        if (self::STATUS_UNINITIALIZED_FULL === $this->status) {
            if ($defaultProperties = array_diff_key(GhostObjectRegistry::$defaultProperties[$instance::class], (array) $instance)) {
                Hydrator::hydrate($instance, $defaultProperties);
            }

            $this->status = self::STATUS_INITIALIZED_FULL;
            ($this->initializer)($instance);

            return true;
        }

        $value = ($this->initializer)(...[$instance, $propertyName, $propertyScope]);

        $propertyScope ??= $instance::class;
        $accessor = GhostObjectRegistry::$classAccessors[$propertyScope] ??= GhostObjectRegistry::getClassAccessors($propertyScope);

        $accessor['set']($instance, $propertyName, $value);

        return false;
    }
}
