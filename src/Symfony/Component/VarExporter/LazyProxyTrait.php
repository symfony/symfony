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

trait LazyProxyTrait
{
    /**
     * Creates a lazy-loading virtual proxy.
     *
     * @param \Closure():object $initializer Returns the proxied object
     * @param static|null       $instance
     */
    public static function createLazyProxy(\Closure $initializer, object $instance = null): static
    {
        $instance ??= (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        \ReflectionLazyObject::makeLazy($instance, $initializer, \ReflectionLazyObject::STRATEGY_VIRTUAL);

        $initializersRegistry = LazyObjectRegistry::$initializers ??= new \WeakMap();
        $initializersRegistry[$instance] = [$initializer, \ReflectionLazyObject::STRATEGY_VIRTUAL];

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
    public function initializeLazyObject(): parent
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
