<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * MemcachedStore is a StoreInterface implementation using Memcached as store engine.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class MemcachedStore implements StoreInterface
{
    private static $defaultClientOptions = array(
        'persistent_id' => null,
        'username' => null,
        'password' => null,
    );

    private $memcached;
    private $initialTtl;
    /** @var bool */
    private $useExtendedReturn;

    public static function isSupported()
    {
        return extension_loaded('memcached');
    }

    /**
     * @param \Memcached $memcached
     * @param int        $initialTtl the expiration delay of locks in seconds
     */
    public function __construct(\Memcached $memcached, $initialTtl = 300)
    {
        if (!static::isSupported()) {
            throw new InvalidArgumentException('Memcached extension is required');
        }

        if ($initialTtl < 1) {
            throw new InvalidArgumentException(sprintf('%s() expects a strictly positive TTL. Got %d.', __METHOD__, $initialTtl));
        }

        $this->memcached = $memcached;
        $this->initialTtl = $initialTtl;
    }

    /**
     * Creates a Memcached instance.
     *
     * By default, the binary protocol, block, and libketama compatible options are enabled.
     *
     * Example DSN:
     * - 'memcached://user:pass@localhost?weight=33'
     * - array(array('localhost', 11211, 33))
     *
     * @param string $dsn     A server or A DSN
     * @param array  $options An array of options
     *
     * @return \Memcached
     *
     * @throws \ErrorEception When invalid options or server are provided
     */
    public static function createConnection($server, array $options = array())
    {
        if (!static::isSupported()) {
            throw new InvalidArgumentException('Memcached extension is required');
        }
        set_error_handler(function ($type, $msg, $file, $line) { throw new \ErrorException($msg, 0, $type, $file, $line); });
        try {
            $options += static::$defaultClientOptions;
            $client = new \Memcached($options['persistent_id']);
            $username = $options['username'];
            $password = $options['password'];

            // parse any DSN in $server
            if (is_string($server)) {
                if (0 !== strpos($server, 'memcached://')) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s does not start with "memcached://"', $server));
                }
                $params = preg_replace_callback('#^memcached://(?:([^@]*+)@)?#', function ($m) use (&$username, &$password) {
                    if (!empty($m[1])) {
                        list($username, $password) = explode(':', $m[1], 2) + array(1 => null);
                    }

                    return 'file://';
                }, $server);
                if (false === $params = parse_url($params)) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s', $server));
                }
                if (!isset($params['host']) && !isset($params['path'])) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s', $server));
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
                    $options = $query + $options;
                }

                $server = array($params['host'], $params['port'], $params['weight']);
            }

            // set client's options
            unset($options['persistent_id'], $options['username'], $options['password'], $options['weight']);
            $options = array_change_key_case($options, CASE_UPPER);
            $client->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $client->setOption(\Memcached::OPT_NO_BLOCK, false);
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

            // set client's servers, taking care of persistent connections
            if (!$client->isPristine()) {
                $oldServers = array();
                foreach ($client->getServerList() as $server) {
                    $oldServers[] = array($server['host'], $server['port']);
                }

                $newServers = array();
                if (1 < count($server)) {
                    $server = array_values($server);
                    unset($server[2]);
                    $server[1] = (int) $server[1];
                }
                $newServers[] = $server;

                if ($oldServers !== $newServers) {
                    // before resetting, ensure $servers is valid
                    $client->addServers(array($server));
                    $client->resetServerList();
                }
            }
            $client->addServers(array($server));

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
    public function save(Key $key)
    {
        $token = $this->getToken($key);
        $key->reduceLifetime($this->initialTtl);
        if (!$this->memcached->add((string) $key, $token, (int) ceil($this->initialTtl))) {
            // the lock is already acquired. It could be us. Let's try to put off.
            $this->putOffExpiration($key, $this->initialTtl);
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to store the "%s" lock.', $key));
        }
    }

    public function waitAndSave(Key $key)
    {
        throw new InvalidArgumentException(sprintf('The store "%s" does not supports blocking locks.', get_class($this)));
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException(sprintf('%s() expects a TTL greater or equals to 1. Got %s.', __METHOD__, $ttl));
        }

        // Interface defines a float value but Store required an integer.
        $ttl = (int) ceil($ttl);

        $token = $this->getToken($key);

        list($value, $cas) = $this->getValueAndCas($key);

        $key->reduceLifetime($ttl);
        // Could happens when we ask a putOff after a timeout but in luck nobody steal the lock
        if (\Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
            if ($this->memcached->add((string) $key, $token, $ttl)) {
                return;
            }

            // no luck, with concurrency, someone else acquire the lock
            throw new LockConflictedException();
        }

        // Someone else steal the lock
        if ($value !== $token) {
            throw new LockConflictedException();
        }

        if (!$this->memcached->cas($cas, (string) $key, $token, $ttl)) {
            throw new LockConflictedException();
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to put off the expiration of the "%s" lock within the specified time.', $key));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $token = $this->getToken($key);

        list($value, $cas) = $this->getValueAndCas($key);

        if ($value !== $token) {
            // we are not the owner of the lock. Nothing to do.
            return;
        }

        // To avoid concurrency in deletion, the trick is to extends the TTL then deleting the key
        if (!$this->memcached->cas($cas, (string) $key, $token, 2)) {
            // Someone steal our lock. It does not belongs to us anymore. Nothing to do.
            return;
        }

        // Now, we are the owner of the lock for 2 more seconds, we can delete it.
        $this->memcached->delete((string) $key);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        return $this->memcached->get((string) $key) === $this->getToken($key);
    }

    /**
     * Retrieve an unique token for the given key.
     *
     * @param Key $key
     *
     * @return string
     */
    private function getToken(Key $key)
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }

    private function getValueAndCas(Key $key)
    {
        if (null === $this->useExtendedReturn) {
            $this->useExtendedReturn = version_compare(phpversion('memcached'), '2.9.9', '>');
        }

        if ($this->useExtendedReturn) {
            $extendedReturn = $this->memcached->get((string) $key, null, \Memcached::GET_EXTENDED);
            if (\Memcached::GET_ERROR_RETURN_VALUE === $extendedReturn) {
                return array($extendedReturn, 0.0);
            }

            return array($extendedReturn['value'], $extendedReturn['cas']);
        }

        $cas = 0.0;
        $value = $this->memcached->get((string) $key, null, $cas);

        return array($value, $cas);
    }
}
