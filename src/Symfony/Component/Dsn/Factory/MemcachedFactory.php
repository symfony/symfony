<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Factory;

use Symfony\Component\Dsn\Configuration\Dsn;
use Symfony\Component\Dsn\Configuration\Path;
use Symfony\Component\Dsn\Configuration\Url;
use Symfony\Component\Dsn\ConnectionFactoryInterface;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Dsn\Exception\FunctionNotSupportedException;
use Symfony\Component\Dsn\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class MemcachedFactory implements ConnectionFactoryInterface
{
    private static $defaultClientOptions = [
        'class' => null,
        'persistent_id' => null,
        'username' => null,
        'password' => null,
        'serializer' => 'php',
    ];

    /**
     * Example DSN strings.
     *
     * - memcached://localhost:11222?retry_timeout=10
     * - memcached(memcached://127.0.0.1)?persistent_id=foobar
     * - memcached(memcached://127.0.0.1 memcached://127.0.0.2?retry_timeout=10)?persistent_id=foobar
     */
    public static function create(string $dsnString): object
    {
        $rootDsn = DsnParser::parseFunc($dsnString);
        if ('dsn' !== $rootDsn->getName() && 'memcached' !== $rootDsn->getName()) {
            throw new FunctionNotSupportedException($dsnString, $rootDsn->getName());
        }

        set_error_handler(function ($type, $msg, $file, $line) { throw new \ErrorException($msg, 0, $type, $file, $line); });

        try {
            $options = $rootDsn->getParameters() + static::$defaultClientOptions;

            $class = null === $options['class'] ? \Memcached::class : $options['class'];
            unset($options['class']);
            if (is_a($class, \Memcached::class, true)) {
                $client = new $class($options['persistent_id']);
            } elseif (class_exists($class, false)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Memcached".', $class));
            } else {
                throw new InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
            }

            $username = $options['username'];
            $password = $options['password'];

            $servers = [];
            foreach ($rootDsn->getArguments() as $dsn) {
                if (!$dsn instanceof Dsn) {
                    throw new InvalidArgumentException('Only one DSN function is allowed.');
                }

                if ('memcached' !== $dsn->getScheme()) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: "%s" does not start with "memcached://".', $dsn));
                }

                $username = $dsn->getUser() ?? $username;
                $password = $dsn->getPassword() ?? $password;
                $path = $dsn->getPath();
                $params['weight'] = 0;

                if (null !== $path && preg_match('#/(\d+)$#', $path, $m)) {
                    $params['weight'] = $m[1];
                    $path = substr($path, 0, -\strlen($m[0]));
                }

                if ($dsn instanceof Url) {
                    $servers[] = [$dsn->getHost(), $dsn->getPort() ?? 11211, $params['weight']];
                } elseif ($dsn instanceof Path) {
                    $params['host'] = $path;
                    $servers[] = [$path, null, $params['weight']];
                }

                $hosts = [];
                foreach ($dsn->getParameter('host', []) as $host => $weight) {
                    if (false === $port = strrpos($host, ':')) {
                        $hosts[$host] = [$host, 11211, (int) $weight];
                    } else {
                        $hosts[$host] = [substr($host, 0, $port), (int) substr($host, 1 + $port), (int) $weight];
                    }
                }
                $servers = array_merge($servers, array_values($hosts));

                $params += $dsn->getParameters();
                $options = $dsn->getParameters() + $options;
            }

            // set client's options
            unset($options['host'], $options['persistent_id'], $options['username'], $options['password'], $options['weight'], $options['lazy']);
            $options = array_change_key_case($options, CASE_UPPER);
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
                $opt = \constant('Memcached::OPT_'.$name);

                unset($options[$name]);
                $options[$opt] = $value;
            }
            $client->setOptions($options);

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

    public static function supports(string $dsn): bool
    {
        return 0 !== strpos($dsn, 'memcached:');
    }
}
