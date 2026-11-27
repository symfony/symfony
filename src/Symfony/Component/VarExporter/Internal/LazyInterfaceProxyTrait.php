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

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\VarExporter\Internal\LazyObjectRegistry as Registry;

/**
 * @internal
 */
trait LazyInterfaceProxyTrait
{
    use LazyDecoratorTrait;

    public static function createLazyProxy(\Closure $initializer, ?object $instance = null): static
    {
        $class = $instance ? $instance::class : static::class;
        $r = Registry::$classReflectors[$class] ??= new \ReflectionClass($class);

        if ($r->isFinal()) {
            // PHP preserves initialized readonly properties when resetting final classes.
            if (self::class === $class && \defined($class.'::LAZY_OBJECT_PROPERTY_SCOPES')) {
                Registry::$propertyScopes[$class] ??= $class::LAZY_OBJECT_PROPERTY_SCOPES;
            }

            $instance ??= $r->newInstanceWithoutConstructor();
            $state = $instance->lazyObjectState ??= new LazyObjectState();
            $state->initializer = null;
            unset($state->realInstance);

            foreach (Registry::$classResetters[$class] ??= Registry::getClassResetters($class) as $reset) {
                $reset($instance, []);
            }
            $state->initializer = $initializer;

            return $instance;
        }

        $initialize = static function ($proxy) use ($initializer) {
            $proxy->lazyObjectState = new LazyObjectState();
            $proxy->lazyObjectState->initializer = $initializer;

            foreach (Registry::$classResetters[$proxy::class] ??= Registry::getClassResetters($proxy::class) as $reset) {
                $reset($proxy, ["\0".self::class."\0lazyObjectState" => true]);
            }

            $proxy->lazyObjectState->realInstance = $initializer();
        };

        if ($instance) {
            $r->markLazyObjectAsInitialized($instance);
            $r->resetAsLazyGhost($instance, $initialize, \ReflectionClass::SKIP_DESTRUCTOR);
        } else {
            $instance = $r->newLazyGhost($initialize);
        }

        return $instance;
    }

    #[Ignore]
    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        $r = Registry::$classReflectors[$this::class] ??= new \ReflectionClass($this);

        return ($r->isFinal() || !$r->isUninitializedLazyObject($this)) && isset($this->lazyObjectState->realInstance);
    }

    public function resetLazyObject(): bool
    {
        $r = Registry::$classReflectors[$this::class] ??= new \ReflectionClass($this);

        if ($r->isUninitializedLazyObject($this)) {
            return true;
        }

        if (!isset($this->lazyObjectState->initializer)) {
            return false;
        }

        if ($r->isFinal()) {
            unset($this->lazyObjectState->realInstance);
        } else {
            self::createLazyProxy($this->lazyObjectState->initializer, $this);
        }

        return true;
    }
}
