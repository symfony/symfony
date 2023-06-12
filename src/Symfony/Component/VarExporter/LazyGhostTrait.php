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

use Symfony\Component\VarExporter\Internal\LazyObjectRegistry;

trait LazyGhostTrait
{
    /**
     * Creates a lazy-loading ghost instance.
     *
     * Skipped properties should be indexed by their array-cast identifier, see
     * https://php.net/manual/language.types.array#language.types.array.casting
     *
     * @param (\Closure(static):void   $initializer       The closure should initialize the object it receives as argument
     * @param array<string, true>|null $skippedProperties An array indexed by the properties to skip, a.k.a. the ones
     *                                                    that the initializer doesn't initialize, if any
     * @param static|null              $instance
     */
    public static function createLazyGhost(\Closure $initializer, array $skippedProperties = null, object $instance = null): static
    {
        $instance ??= (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $r = \ReflectionLazyObject::makeLazy($instance, $initializer);

        $initializersRegistry = LazyObjectRegistry::$initializers ??= new \WeakMap();
        $initializersRegistry[$instance] = [$initializer, \ReflectionLazyObject::STRATEGY_GHOST];

        foreach ($skippedProperties ?? [] as $property => $v) {
            if ("\0" === $property[0]) {
                [, $class, $property] = explode("\0", $property, 3);

                if ('*' === $class) {
                    $class = null;
                }
            } else {
                $class = null;
            }
            $r->skipProperty($property, $class);
        }

        return $instance;
    }

    /**
     * Returns whether the object is initialized.
     *
     * @param $partial Whether partially initialized objects should be considered as initialized
     */
    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        return !\ReflectionLazyObject::isLazyObject($this);
    }

    /**
     * Forces initialization of a lazy object and returns it.
     */
    public function initializeLazyObject(): static
    {
        \ReflectionLazyObject::fromInstance($this)?->initialize();

        return $this;
    }

    /**
     * @return bool Returns false when the object cannot be reset, ie when it's not a lazy object
     */
    public function resetLazyObject(): bool
    {
        if (![$initializer, $strategy] = LazyObjectRegistry::$initializers[$this] ?? null) {
            return false;
        }

        \ReflectionLazyObject::makeLazy($this, $initializer, $strategy);

        return true;
    }
}
