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

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait MemcachedTrait
{
    private static $defaultClientOptions = array(
        'persistent_id' => null,
        'username' => null,
        'password' => null,
    );

    private $client;

    public static function isSupported()
    {
        return extension_loaded('memcached') && version_compare(phpversion('memcached'), '2.2.0', '>=');
    }

    private function init(\Memcached $client, $namespace, $defaultLifetime)
    {
        if (!static::isSupported()) {
            throw new CacheException('Memcached >= 2.2.0 is required');
        }
        $opt = $client->getOption(\Memcached::OPT_SERIALIZER);
        if (\Memcached::SERIALIZER_PHP !== $opt && \Memcached::SERIALIZER_IGBINARY !== $opt) {
            throw new CacheException('MemcachedAdapter: "serializer" option must be "php" or "igbinary".');
        }
        $this->maxIdLength -= strlen($client->getOption(\Memcached::OPT_PREFIX_KEY));

        parent::__construct($namespace, $defaultLifetime);
        $this->client = $client;
    }

    /**
     * Creates a Memcached instance.
     *
     * By default, the binary protocol, no block, and libketama compatible options are enabled.
     *
     * Examples for servers:
     * - 'memcached://user:pass@localhost?weight=33'
     * - array(array('localhost', 11211, 33))
     *
     * @param array[]|string|string[] An array of servers, a DSN, or an array of DSNs
     * @param array                   An array of options
     *
     * @return \Memcached
     *
     * @throws \ErrorEception When invalid options or servers are provided
     */
    public static function createConnection($servers, array $options = array())
    {
        if (is_string($servers)) {
            $servers = array($servers);
        } elseif (!is_array($servers)) {
            throw new InvalidArgumentException(sprintf('MemcachedAdapter::createClient() expects array or string as first argument, %s given.', gettype($servers)));
        }
        if (!static::isSupported()) {
            throw new CacheException('Memcached >= 2.2.0 is required');
        }
        set_error_handler(function ($type, $msg, $file, $line) { throw new \ErrorException($msg, 0, $type, $file, $line); });
        try {
            $options += static::$defaultClientOptions;
            $client = new \Memcached($options['persistent_id']);
            $username = $options['username'];
            $password = $options['password'];
            unset($options['persistent_id'], $options['username'], $options['password']);
            $options = array_change_key_case($options, CASE_UPPER);

            // set client's options
            $client->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $client->setOption(\Memcached::OPT_NO_BLOCK, true);
            if (!array_key_exists('LIBKETAMA_COMPATIBLE', $options) && !array_key_exists(\Memcached::OPT_LIBKETAMA_COMPATIBLE, $options)) {
                $client->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
            }
            foreach ($options as $name => $value) {
                if (is_int($name)) {
                    continue;
                }
                if ('HASH' === $name || 'SERIALIZER' === $name || 'DISTRIBUTION' === $name) {
                    $value = constant('Memcached::'.$name.'_'.strtoupper($value));
                }
                $opt = constant('Memcached::OPT_'.$name);

                unset($options[$name]);
                $options[$opt] = $value;
            }
            $client->setOptions($options);

            // parse any DSN in $servers
            foreach ($servers as $i => $dsn) {
                if (is_array($dsn)) {
                    continue;
                }
                if (0 !== strpos($dsn, 'memcached://')) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s does not start with "memcached://"', $dsn));
                }
                $params = preg_replace_callback('#^memcached://(?:([^@]*+)@)?#', function ($m) use (&$username, &$password) {
                    if (!empty($m[1])) {
                        list($username, $password) = explode(':', $m[1], 2) + array(1 => null);
                    }

                    return 'file://';
                }, $dsn);
                if (false === $params = parse_url($params)) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s', $dsn));
                }
                if (!isset($params['host']) && !isset($params['path'])) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s', $dsn));
                }
                if (isset($params['path']) && preg_match('#/(\d+)$#', $params['path'], $m)) {
                    $params['weight'] = $m[1];
                    $params['path'] = substr($params['path'], 0, -strlen($m[0]));
                }
                $params += array(
                    'host' => isset($params['host']) ? $params['host'] : $params['path'],
                    'port' => isset($params['host']) ? 11211 : null,
                    'weight' => 0,
                );
                if (isset($params['query'])) {
                    parse_str($params['query'], $query);
                    $params += $query;
                }

                $servers[$i] = array($params['host'], $params['port'], $params['weight']);
            }

            // set client's servers, taking care of persistent connections
            if (!$client->isPristine()) {
                $oldServers = array();
                foreach ($client->getServerList() as $server) {
                    $oldServers[] = array($server['host'], $server['port']);
                }

                $newServers = array();
                foreach ($servers as $server) {
                    if (1 < count($server)) {
                        $server = array_values($server);
                        unset($server[2]);
                        $server[1] = (int) $server[1];
                    }
                    $newServers[] = $server;
                }

                if ($oldServers !== $newServers) {
                    // before resetting, ensure $servers is valid
                    $client->addServers($servers);
                    $client->resetServerList();
                }
            }
            $client->addServers($servers);

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
    protected function doSave(array $values, $lifetime)
    {
        return $this->checkResultCode($this->client->setMulti($values, $lifetime));
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        return $this->checkResultCode($this->client->getMulti($ids));
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return false !== $this->client->get($id) || $this->checkResultCode(\Memcached::RES_SUCCESS === $this->client->getResultCode());
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $ok = true;
        foreach ($this->checkResultCode($this->client->deleteMulti($ids)) as $result) {
            if (\Memcached::RES_SUCCESS !== $result && \Memcached::RES_NOTFOUND !== $result) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        return $this->checkResultCode($this->client->flush());
    }

    private function checkResultCode($result)
    {
        $code = $this->client->getResultCode();

        if (\Memcached::RES_SUCCESS === $code || \Memcached::RES_NOTFOUND === $code) {
            return $result;
        }

        throw new CacheException(sprintf('MemcachedAdapter client error: %s.', strtolower($this->client->getResultMessage())));
    }
}
