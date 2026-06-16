<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;

/**
 * Creates a cache adapter from a DSN, resolving the backend from the DSN scheme at runtime.
 *
 * Implementations are collected by {@see DsnAdapterFactory}; registering a new one (tagged
 * "cache.adapter_factory" in FrameworkBundle) is enough to support an extra scheme, the same
 * way Messenger transports are discovered through {@see \Symfony\Component\Messenger\Transport\TransportFactoryInterface}.
 *
 * @author Ousama Ben Younes <benyounes.ousama@gmail.com>
 */
interface AdapterFactoryInterface
{
    public function createAdapter(#[\SensitiveParameter] string $dsn, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null): AdapterInterface;

    public function supports(#[\SensitiveParameter] string $dsn): bool;
}
