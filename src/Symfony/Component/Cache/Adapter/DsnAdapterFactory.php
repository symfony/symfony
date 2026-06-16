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

use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

/**
 * Builds a cache adapter from a DSN by delegating to the first registered {@see AdapterFactoryInterface}
 * that supports the DSN scheme.
 *
 * Mirrors {@see \Symfony\Component\Messenger\Transport\TransportFactory}: the scheme is resolved at runtime,
 * so a DSN coming from an environment variable (e.g. "%env(CACHE_DSN)%") selects the backend at instantiation
 * time rather than at container-compile time.
 *
 * @author Ousama Ben Younes <benyounes.ousama@gmail.com>
 */
class DsnAdapterFactory implements AdapterFactoryInterface
{
    /**
     * @param iterable<AdapterFactoryInterface> $factories
     */
    public function __construct(
        private readonly iterable $factories,
    ) {
    }

    public function createAdapter(#[\SensitiveParameter] string $dsn, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null): AdapterInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->createAdapter($dsn, $namespace, $defaultLifetime, $marshaller);
            }
        }

        throw new InvalidArgumentException(\sprintf('No cache adapter supports the DSN scheme "%s://".', strstr($dsn, ':', true) ?: $dsn));
    }

    public function supports(#[\SensitiveParameter] string $dsn): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return true;
            }
        }

        return false;
    }
}
