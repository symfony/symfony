<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dsn\Factory;

use Symfony\Component\Dsn\Exception\InvalidArgumentException;

/**
 * Factory for Memcached connections.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class MemcachedConnectionFactory
{
    private static $defaultClientOptions = array(
        'class' => null,
        'persistent_id' => null,
        'username' => null,
        'password' => null,
        'serializer' => 'php',
    );

    /**
     * Creates a Memcached instance.
     *
     * By default, the binary protocol, no block, and libketama compatible options are enabled.
     *
     * Examples for servers:
     *   - memcached://localhost
     *   - memcached://example.com:1234
     *   - memcached://user:pass@example.com
     *   - memcached://localhost?weight=25
     *   - memcached:///var/run/memcached.sock?weight=25
     *   - memcached://user:password@/var/run/memcached.socket?weight=25
     *   - array('memcached://server1', 'memcached://server2')
     *
     * @param string|string[] A DSN, or an array of DSNs
     * @param array           An array of options
     *
     * @return \Memcached According to the "class" option
     *
     * @throws \ErrorException When invalid options or servers are provided
     */
    public static function createConnection($dsns, array $options = array())
    {
        set_error_handler(function ($type, $msg, $file, $line) { throw new \ErrorException($msg, 0, $type, $file, $line); });
        try {
            $options += static::$defaultClientOptions;

            $class = null === $options['class'] ? \Memcached::class : $options['class'];
            unset($options['class']);
            if (is_a($class, \Memcached::class, true)) {
                $client = new \Memcached($options['persistent_id']);
            } elseif (class_exists($class, false)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Memcached"', $class));
            } else {
                throw new InvalidArgumentException(sprintf('Class "%s" does not exist', $class));
            }

            $username = $options['username'];
            $password = $options['password'];

            // parse any DSN in $dsns
            $servers = array();
            foreach ((array) $dsns as $dsn) {
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
                    $options = $query + $options;
                }

                $servers[] = array($params['host'], $params['port'], $params['weight']);
            }

            // set client's options
            unset($options['persistent_id'], $options['username'], $options['password'], $options['weight']);
            $options = array_change_key_case($options, CASE_UPPER);
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
}
