<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

/**
 * @internal
 */
trait RedisProxyTrait
{
    private \Closure $initializer;
    private ?parent $realInstance = null;

    public static function createLazyProxy(\Closure $initializer, ?self $instance = null): static
    {
        $instance ??= (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->realInstance = null;
        $instance->initializer = $initializer;

        return $instance;
    }

    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        return isset($this->realInstance);
    }

    public function initializeLazyObject(): object
    {
        return $this->realInstance ??= ($this->initializer)();
    }

    public function resetLazyObject(): bool
    {
        $this->realInstance = null;

        return true;
    }

    public function __destruct()
    {
    }
}
