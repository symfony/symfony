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
trait LazyDecoratorTrait
{
    #[Ignore]
    private readonly LazyObjectState $lazyObjectState;

    /**
     * Creates a lazy-loading decorator.
     *
     * @param \Closure():object $initializer Returns the proxied object
     * @param static|null       $instance
     */
    public static function createLazyProxy(\Closure $initializer, ?object $instance = null): static
    {
        $class = $instance ? $instance::class : static::class;

        if (self::class === $class && \defined($class.'::LAZY_OBJECT_PROPERTY_SCOPES')) {
            Hydrator::$propertyScopes[$class] ??= $class::LAZY_OBJECT_PROPERTY_SCOPES;
        }

        $instance ??= (Registry::$classReflectors[$class] ??= ($r = new \ReflectionClass($class))->hasProperty('lazyObjectState')
            ? $r
            : throw new \LogicException('Cannot create a lazy proxy for a non-decorator object.')
        )->newInstanceWithoutConstructor();

        $state = $instance->lazyObjectState ??= new LazyObjectState();
        $state->initializer = null;
        unset($state->realInstance);

        foreach (Registry::$classResetters[$class] ??= Registry::getClassResetters($class) as $reset) {
            $reset($instance, []);
        }
        $state->initializer = $initializer;

        return $instance;
    }

    public function __construct(...$args)
    {
        self::createLazyProxy(static fn () => new parent(...$args), $this);
    }

    public function __destruct()
    {
    }

    #[Ignore]
    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        return isset($this->lazyObjectState->realInstance);
    }

    public function initializeLazyObject(): parent
    {
        return $this->lazyObjectState->realInstance;
    }

    public function resetLazyObject(): bool
    {
        if (!isset($this->lazyObjectState->initializer)) {
            return false;
        }
        unset($this->lazyObjectState->realInstance);

        return true;
    }

    public function &__get($name): mixed
    {
        $instance = $this->lazyObjectState->realInstance;
        $class = $this::class;

        $propertyScopes = Hydrator::$propertyScopes[$class] ??= Hydrator::getPropertyScopes($class);
        $notByRef = 0;

        if ([, , , $access] = $propertyScopes[$name] ?? null) {
            $notByRef = $access & Hydrator::PROPERTY_NOT_BY_REF || ($access >> 2) & \ReflectionProperty::IS_PRIVATE_SET;
        }

        if ($notByRef || 2 !== ((Registry::$parentMethods[$class] ??= Registry::getParentMethods($class))['get'] ?: 2)) {
            $value = $instance->$name;

            return $value;
        }

        try {
            return $instance->$name;
        } catch (\Error $e) {
            if (\Error::class !== $e::class || !str_starts_with($e->getMessage(), 'Cannot access uninitialized non-nullable property')) {
                throw $e;
            }

            try {
                $instance->$name = [];

                return $instance->$name;
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
        $this->lazyObjectState->realInstance->$name = $value;
    }

    public function __isset($name): bool
    {
        return isset($this->lazyObjectState->realInstance->$name);
    }

    public function __unset($name): void
    {
        if ($this->lazyObjectState->initializer) {
            unset($this->lazyObjectState->realInstance->$name);
        }
    }

    public function __serialize(): array
    {
        return [$this->lazyObjectState->realInstance];
    }

    public function __unserialize($data): void
    {
        $this->lazyObjectState = new LazyObjectState();
        $this->lazyObjectState->realInstance = $data[0];
    }

    public function __clone(): void
    {
        $this->lazyObjectState->realInstance; // initialize lazy object
        $this->lazyObjectState = clone $this->lazyObjectState;
    }
}
