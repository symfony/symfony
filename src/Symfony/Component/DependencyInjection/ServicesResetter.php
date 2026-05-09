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
 */
class ServicesResetter implements ServicesResetterInterface
{
    /**
     * @param \Traversable<string, object>   $resettableServices
     * @param array<string, string|string[]> $resetMethods
     */
    public function __construct(
        private \Traversable $resettableServices,
        private array $resetMethods,
        private ?\WeakMap $resetMap = null,
    ) {
    }

    public function reset(): void
    {
        foreach ($this->resettableServices as $id => $service) {
            $this->resetInstance($service, (array) $this->resetMethods[$id]);
        }

        if (null !== $this->resetMap) {
            foreach ($this->resetMap as $service => $methods) {
                $this->resetInstance($service, $methods);
            }
        }
    }

    private function resetInstance(object $service, array $methods): void
    {
        if ($this->isUninitializedLazyObject($service)) {
            return;
        }

        foreach ($methods as $resetMethod) {
            if ('?' === $resetMethod[0] && !method_exists($service, $resetMethod = substr($resetMethod, 1))) {
                continue;
            }

            $service->$resetMethod();
        }
    }

    private function isUninitializedLazyObject(object $service): bool
    {
        if ($service instanceof LazyObjectInterface && !$service->isLazyObjectInitialized(true)) {
            return true;
        }

        /** @psalm-suppress UndefinedClass */
        if ($service instanceof LazyLoadingInterface && !$service->isProxyInitialized()) {
            return true;
        }

        return (new \ReflectionClass($service))->isUninitializedLazyObject($service);
    }
}
