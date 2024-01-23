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

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class MemcachedAdapter extends AbstractAdapter
{
    /**
     * We are replacing characters that are illegal in Memcached keys with reserved characters from
     * {@see \Symfony\Contracts\Cache\ItemInterface::RESERVED_CHARACTERS} that are legal in Memcached.
     * Note: don’t use {@see \Symfony\Component\Cache\Adapter\AbstractAdapter::NS_SEPARATOR}.
     */
    private const RESERVED_MEMCACHED = " \n\r\t\v\f\0";
    private const RESERVED_PSR6 = '@()\{}/';

    protected $maxIdLength = 250;

    private $marshaller;
    private $client;
    private $lazyClient;

    /**
     * Using a MemcachedAdapter with a TagAwareAdapter for storing tags is discouraged.
     * Using a RedisAdapter is recommended instead. If you cannot do otherwise, be aware that:
     * - the Memcached::OPT_BINARY_PROTOCOL must be enabled
     *   (that's the default when using MemcachedAdapter::createConnection());
     * - tags eviction by Memcached's LRU algorithm will break by-tags invalidation;
     *   your Memcached memory should be large enough to never trigger LRU.
     *
     * Using a MemcachedAdapter as a pure items store is fine.
     */
    public function __construct(\Memcached $client, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null)
    {
        if (!static::isSupported()) {
            throw new CacheException('Memcached '.(\PHP_VERSION_ID >= 80100 ? '> 3.1.5' : '>= 2.2.0').' is required.');
        }
        if ('Memcached' === \get_class($client)) {
            $opt = $client->getOption(\Memcached::OPT_SERIALIZER);
            if (\Memcached::SERIALIZER_PHP !== $opt && \Memcached::SERIALIZER_IGBINARY !== $opt) {
                throw new CacheException('MemcachedAdapter: "serializer" option must be "php" or "igbinary".');
            }
            $this->maxIdLength -= \strlen($client->getOption(\Memcached::OPT_PREFIX_KEY));
            $this->client = $client;
        } else {
            $this->lazyClient = $client;
        }

        parent::__construct($namespace, $defaultLifetime);
        $this->enableVersioning();
        $this->marshaller = $marshaller ?? new DefaultMarshaller();
    }

    public static function isSupported()
    {
        return \extension_loaded('memcached') && version_compare(phpversion('memcached'), \PHP_VERSION_ID >= 80100 ? '3.1.6' : '2.2.0', '>=');
    }

    /**
     * Creates a Memcached instance.
     *
     * By default, the binary protocol, no block, and libketama compatible options are enabled.
     *
     * Examples for servers:
     * - 'memcached://user:pass@localhost?weight=33'
     * - [['localhost', 11211, 33]]
     *
     * @param array[]|string|string[] $servers An array of servers, a DSN, or an array of DSNs
     *
     * @return \Memcached
     *
     * @throws \ErrorException When invalid options or servers are provided
     */
    public static function createConnection($servers, array $options = [])
    {
        if (\is_string($servers)) {
            $servers = [$servers];
        } elseif (!\is_array($servers)) {
            throw new InvalidArgumentException(sprintf('MemcachedAdapter::createClient() expects array or string as first argument, "%s" given.', get_debug_type($servers)));
        }
        if (!static::isSupported()) {
            throw new CacheException('Memcached '.(\PHP_VERSION_ID >= 80100 ? '> 3.1.5' : '>= 2.2.0').' is required.');
        }
        set_error_handler(function ($type, $msg, $file, $line) { throw new \ErrorException($msg, 0, $type, $file, $line); });
        try {
            $client = new \Memcached($options['persistent_id'] ?? null);
            $username = $options['username'] ?? null;
            $password = $options['password'] ?? null;

            // parse any DSN in $servers
            foreach ($servers as $i => $dsn) {
                if (\is_array($dsn)) {
                    continue;
                }
                if (!str_starts_with($dsn, 'memcached:')) {
                    throw new InvalidArgumentException('Invalid Memcached DSN: it does not start with "memcached:".');
                }
                $params = preg_replace_callback('#^memcached:(//)?(?:([^@]*+)@)?#', function ($m) use (&$username, &$password) {
                    if (!empty($m[2])) {
                        [$username, $password] = explode(':', $m[2], 2) + [1 => null];
                        $username = rawurldecode($username);
                        $password = null !== $password ? rawurldecode($password) : null;
                    }

                    return 'file:'.($m[1] ?? '');
                }, $dsn);
                if (false === $params = parse_url($params)) {
                    throw new InvalidArgumentException('Invalid Memcached DSN.');
                }
                $query = $hosts = [];
                if (isset($params['query'])) {
                    parse_str($params['query'], $query);

                    if (isset($query['host'])) {
                        if (!\is_array($hosts = $query['host'])) {
                            throw new InvalidArgumentException('Invalid Memcached DSN: query parameter "host" must be an array.');
                        }
                        foreach ($hosts as $host => $weight) {
                            if (false === $port = strrpos($host, ':')) {
                                $hosts[$host] = [$host, 11211, (int) $weight];
                            } else {
                                $hosts[$host] = [substr($host, 0, $port), (int) substr($host, 1 + $port), (int) $weight];
                            }
                        }
                        $hosts = array_values($hosts);
                        unset($query['host']);
                    }
                    if ($hosts && !isset($params['host']) && !isset($params['path'])) {
                        unset($servers[$i]);
                        $servers = array_merge($servers, $hosts);
                        continue;
                    }
                }
                if (!isset($params['host']) && !isset($params['path'])) {
                    throw new InvalidArgumentException('Invalid Memcached DSN: missing host or path.');
                }
                if (isset($params['path']) && preg_match('#/(\d+)$#', $params['path'], $m)) {
                    $params['weight'] = $m[1];
                    $params['path'] = substr($params['path'], 0, -\strlen($m[0]));
                }
                $params += [
                    'host' => $params['host'] ?? $params['path'],
                    'port' => isset($params['host']) ? 11211 : null,
                    'weight' => 0,
                ];
                if ($query) {
                    $params += $query;
                    $options = $query + $options;
                }

                $servers[$i] = [$params['host'], $params['port'], $params['weight']];

                if ($hosts) {
                    $servers = array_merge($servers, $hosts);
                }
            }

            // set client's options
            unset($options['persistent_id'], $options['username'], $options['password'], $options['weight'], $options['lazy']);
            $options = array_change_key_case($options, \CASE_UPPER);
            $client->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $client->setOption(\Memcached::OPT_NO_BLOCK, true);
            $client->setOption(\Memcached::OPT_TCP_NODELAY, true);
            if (!\array_key_exists('LIBKETAMA_COMPATIBLE', $options) && !\array_key_exists(\Memcached::OPT_LIBKETAMA_COMPATIBLE, $options)) {
                $client->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
            }
            foreach ($options as $name => $value) {
                if (\is_int($name)) {
                    continue;
                }
                if ('HASH' === $name || 'SERIALIZER' === $name || 'DISTRIBUTION' === $name) {
                    $value = \constant('Memcached::'.$name.'_'.strtoupper($value));
                }
                unset($options[$name]);

                if (\defined('Memcached::OPT_'.$name)) {
                    $options[\constant('Memcached::OPT_'.$name)] = $value;
                }
            }
            $client->setOptions($options + [\Memcached::OPT_SERIALIZER => \Memcached::SERIALIZER_PHP]);

            // set client's servers, taking care of persistent connections
            if (!$client->isPristine()) {
                $oldServers = [];
                foreach ($client->getServerList() as $server) {
                    $oldServers[] = [$server['host'], $server['port']];
                }

                $newServers = [];
                foreach ($servers as $server) {
                    if (1 < \count($server)) {
                        $server = array_values($server);
                        unset($server[2]);
                        $server[1] = (int) $server[1];
                    }
                    $newServers[] = $server;
                }

                if ($oldServers !== $newServers) {
                    $client->resetServerList();
                    $client->addServers($servers);
                }
            } else {
                $client->addServers($servers);
            }

            if (null !== $username || null !== $password) {
                if (!method_exists($client, 'setSaslAuthData')) {
                    trigger_error('Missing SASL support: the memcached extension must be compiled with --enable-memcached-sasl.');
                }
                $client->setSaslAuthData($username, $password);
            }

            return $client;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, int $lifetime)
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        if ($lifetime && $lifetime > 30 * 86400) {
            $lifetime += time();
        }

        $encodedValues = [];
        foreach ($values as $key => $value) {
            $encodedValues[self::encodeKey($key)] = $value;
        }

        return $this->checkResultCode($this->getClient()->setMulti($encodedValues, $lifetime)) ? $failed : false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        try {
            $encodedIds = array_map([__CLASS__, 'encodeKey'], $ids);

            $encodedResult = $this->checkResultCode($this->getClient()->getMulti($encodedIds));

            $result = [];
            foreach ($encodedResult as $key => $value) {
                $result[self::decodeKey($key)] = $this->marshaller->unmarshall($value);
            }

            return $result;
        } catch (\Error $e) {
            throw new \ErrorException($e->getMessage(), $e->getCode(), \E_ERROR, $e->getFile(), $e->getLine());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave(string $id)
    {
        return false !== $this->getClient()->get(self::encodeKey($id)) || $this->checkResultCode(\Memcached::RES_SUCCESS === $this->client->getResultCode());
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $ok = true;
        $encodedIds = array_map([__CLASS__, 'encodeKey'], $ids);
        foreach ($this->checkResultCode($this->getClient()->deleteMulti($encodedIds)) as $result) {
            if (\Memcached::RES_SUCCESS !== $result && \Memcached::RES_NOTFOUND !== $result) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear(string $namespace)
    {
        return '' === $namespace && $this->getClient()->flush();
    }

    private function checkResultCode($result)
    {
        $code = $this->client->getResultCode();

        if (\Memcached::RES_SUCCESS === $code || \Memcached::RES_NOTFOUND === $code) {
            return $result;
        }

        throw new CacheException('MemcachedAdapter client error: '.strtolower($this->client->getResultMessage()));
    }

    private function getClient(): \Memcached
    {
        if ($this->client) {
            return $this->client;
        }

        $opt = $this->lazyClient->getOption(\Memcached::OPT_SERIALIZER);
        if (\Memcached::SERIALIZER_PHP !== $opt && \Memcached::SERIALIZER_IGBINARY !== $opt) {
            throw new CacheException('MemcachedAdapter: "serializer" option must be "php" or "igbinary".');
        }
        if ('' !== $prefix = (string) $this->lazyClient->getOption(\Memcached::OPT_PREFIX_KEY)) {
            throw new CacheException(sprintf('MemcachedAdapter: "prefix_key" option must be empty when using proxified connections, "%s" given.', $prefix));
        }

        return $this->client = $this->lazyClient;
    }

    private static function encodeKey(string $key): string
    {
        return strtr($key, self::RESERVED_MEMCACHED, self::RESERVED_PSR6);
    }

    private static function decodeKey(string $key): string
    {
        return strtr($key, self::RESERVED_PSR6, self::RESERVED_MEMCACHED);
    }
}
