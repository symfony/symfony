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
class RedisAdapterFactory implements AdapterFactoryInterface
{
    private const SCHEMES = ['redis', 'rediss', 'valkey', 'valkeys'];

    /**
     * Connections are created lazily so that an unused pool never opens a socket at container build time.
     */
    private const CONNECTION_OPTIONS = ['lazy' => true];

    public function createAdapter(#[\SensitiveParameter] string $dsn, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null): AdapterInterface
    {
        return new RedisAdapter(RedisAdapter::createConnection($dsn, self::CONNECTION_OPTIONS), $namespace, $defaultLifetime, $marshaller);
    }

    public function supports(#[\SensitiveParameter] string $dsn): bool
    {
        return \in_array(strtolower(strstr($dsn, ':', true) ?: ''), self::SCHEMES, true);
    }
}
