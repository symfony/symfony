<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * Resets provided services.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final since Symfony 8.2
 */
class ServicesResetter implements ServicesResetterInterface
{
    /**
     * @var array<class-string, \ReflectionClass>
     */
    private static array $reflectors = [];

    /**
     * @param \Traversable<string, object>                                               $resettableServices
     * @param array<string, string|list<string|array{string, bool, 'flag'|'only'|null}>> $resetMethods
     */
    public function __construct(
        private \Traversable $resettableServices,
        private array $resetMethods,
        private ?\WeakMap $resetMap = null,
    ) {
    }

    /**
     * @param bool $shutdown Whether the services are being shut down
     */
    public function reset(bool $shutdown = false): void
    {
        $throwable = null;

        try {
            foreach ($this->resettableServices as $id => $service) {
                $this->resetInstance($service, (array) $this->resetMethods[$id], $throwable, $shutdown);
            }

            if (null !== $this->resetMap) {
                foreach ($this->resetMap as $service => $methods) {
                    $this->resetInstance($service, $methods, $throwable, $shutdown);
                }
            }
        } finally {
            if ($shutdown) {
                $this->resettableServices = new \EmptyIterator();
                $this->resetMethods = [];
                $this->resetMap = null;
            }
        }

        if (null !== $throwable) {
            throw $throwable;
        }
    }

    /**
     * @param list<string|array{string, bool, 'flag'|'only'|null}> $methods
     */
    private function resetInstance(object $service, array $methods, ?\Throwable &$throwable, bool $shutdown): void
    {
        if ($this->isUninitializedLazyObject($service)) {
            return;
        }

        foreach ($methods as $resetMethod) {
            if (\is_array($resetMethod)) {
                [$resetMethod, $optional, $onShutdown] = $resetMethod;

                if (!$shutdown && 'only' === $onShutdown) {
                    continue;
                }
            } else {
                $onShutdown = null;
                if ($optional = '?' === $resetMethod[0]) {
                    $resetMethod = substr($resetMethod, 1);
                }
            }

            if ($optional && !method_exists($service, $resetMethod)) {
                continue;
            }

            try {
                if ($shutdown && 'flag' === $onShutdown) {
                    $service->$resetMethod(true);
                } else {
                    $service->$resetMethod();
                }
            } catch (\Throwable $e) {
                // failing to reset one service should not prevent resetting the remaining ones
                $throwable ??= $e;
            }
        }
    }

    /** @psalm-suppress UndefinedClass */
    private function isUninitializedLazyObject(object $service): bool
    {
        if ($service instanceof LazyObjectInterface && !$service->isLazyObjectInitialized(true)) {
            return true;
        }

        if ($service instanceof LazyLoadingInterface && !$service->isProxyInitialized()) {
            return true;
        }

        return (self::$reflectors[$service::class] ??= new \ReflectionClass($service))->isUninitializedLazyObject($service);
    }
}
