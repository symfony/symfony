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
 * @author Ousama Ben Younes <benyounes.ousama@gmail.com>
 */
class MemcachedAdapterFactory implements AdapterFactoryInterface
{
    private const SCHEMES = ['memcached'];

    public function createAdapter(#[\SensitiveParameter] string $dsn, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null): AdapterInterface
    {
        return new MemcachedAdapter(MemcachedAdapter::createConnection($dsn), $namespace, $defaultLifetime, $marshaller);
    }

    public function supports(#[\SensitiveParameter] string $dsn): bool
    {
        return \in_array(strtolower(strstr($dsn, ':', true) ?: ''), self::SCHEMES, true);
    }
}
