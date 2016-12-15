<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter\Client;

use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 *
 * @internal
 */
final class MemcachedClient
{
    private static $serverDefaults = array(
        'host' => 'localhost',
        'port' => 11211,
        'weight' => 100,
    );

    private static $optionDefaults = array(
        'compression' => true,
        'binary_protocol' => true,
        'libketama_compatible' => true,
    );

    private $client;
    private $errorLevel;

    public function __construct(array $servers = array(), array $options = array())
    {
        $this->client = new \Memcached(isset($options['persistent_id']) ? $options['persistent_id'] : null);
        $this->setOptions($options);
        $this->setServers($servers);
    }

    /**
     * @return \Memcached
     */
    public static function create($servers = array(), array $options = array())
    {
        return (new static(is_array($servers) ? $servers : array($servers), $options))->getClient();
    }

    public static function isSupported()
    {
        return extension_loaded('memcached') && version_compare(phpversion('memcached'), '2.2.0', '>=');
    }

    public function getClient()
    {
        return $this->client;
    }

    private function setOptions(array $options)
    {
        unset($options['persistent_id']);
        $options += static::$optionDefaults;

        foreach (array_reverse($options) as $named => $value) {
            $this->addOption($named, $value);
        }
    }

    private function addOption($named, $value)
    {
        $this->silenceErrorInitialize();
        $result = $this->client->setOption($this->resolveOptionNamed($named), $this->resolveOptionValue($named, $value));
        $this->silenceErrorRestoreAct(!$result, 'Invalid option: %s=%s', array(var_export($named, true), var_export($value, true)));
    }

    private function resolveOptionNamed($named)
    {
        if (!defined($constant = sprintf('\Memcached::OPT_%s', strtoupper($named)))) {
            throw new InvalidArgumentException(sprintf('Invalid option named: %s', $named));
        }

        return constant($constant);
    }

    private function resolveOptionValue($named, $value)
    {
        $typed = preg_replace('{_.*$}', '', $named);

        if (defined($constant = sprintf('\Memcached::%s_%s', strtoupper($typed), strtoupper($value)))
         || defined($constant = sprintf('\Memcached::%s', strtoupper($value)))) {
            return constant($constant);
        }

        return $value;
    }

    private function setServers(array $dsns)
    {
        foreach ($dsns as $i => $dsn) {
            $this->addServer($i, $dsn);
        }
    }

    private function addServer($i, $dsn)
    {
        if (false === $server = $this->resolveServer($dsn)) {
            throw new InvalidArgumentException(sprintf('Invalid server %d DSN: %s', $i, $dsn));
        }

        if ($this->hasServer($server['host'], $server['port'])) {
            return;
        }

        if (isset($server['user']) && isset($server['port'])) {
            $this->setServerAuthentication($server['user'], $server['pass']);
        }

        $this->client->addServer($server['host'], $server['port'], $server['weight']);
    }

    private function hasServer($host, $port)
    {
        foreach ($this->client->getServerList() as $server) {
            if ($server['host'] === $host && $server['port'] === $port) {
                return true;
            }
        }

        return false;
    }

    private function setServerAuthentication($user, $pass)
    {
        $this->silenceErrorInitialize();
        $result = $this->client->setSaslAuthData($user, $pass);
        $this->silenceErrorRestoreAct(!$result, 'Could not set SASL authentication:');
    }

    private function resolveServer($dsn)
    {
        if (0 !== strpos($dsn, 'memcached')) {
            return false;
        }

        if (false !== $server = $this->resolveServerAsHost($dsn)) {
            return $server;
        }

        return $this->resolveServerAsSock($dsn);
    }

    private function resolveServerAsHost($dsn)
    {
        if (false === $server = parse_url($dsn)) {
            return false;
        }

        return $this->resolveServerCommon($server);
    }

    private function resolveServerAsSock($dsn)
    {
        if (1 !== preg_match('{memcached:\/\/(?:(?<user>.+?):(?<pass>.+?)@)?(?<host>\/[^?]+)(?:\?)?(?<query>.+)?}', $dsn, $server)) {
            return false;
        }

        if (0 === strpos(strrev($server['host']), '/')) {
            $server['host'] = substr($server['host'], 0, -1);
        }

        return $this->resolveServerCommon(array_filter($server, function ($v, $i) {
            return !is_int($i) && !empty($v);
        }, ARRAY_FILTER_USE_BOTH));
    }

    private function resolveServerCommon($server)
    {
        parse_str(isset($server['query']) ? $server['query'] : '', $query);

        $server += array_filter($query, function ($index) {
            return in_array($index, array('weight'));
        }, ARRAY_FILTER_USE_KEY);

        $server += static::$serverDefaults;

        return array_filter($server, function ($index) {
            return in_array($index, array('host', 'port', 'weight', 'user', 'pass'));
        }, ARRAY_FILTER_USE_KEY);
    }

    private function silenceErrorInitialize()
    {
        $this->errorLevel = error_reporting(~E_ALL);
    }

    private function silenceErrorRestoreAct($throwError, $format, array $replacements = array())
    {
        error_reporting($this->errorLevel);

        if ($throwError) {
            $errorRet = error_get_last();
            $errorMsg = isset($errorRet['message']) ? $errorRet['message'] : $this->client->getResultMessage();

            throw new InvalidArgumentException(vsprintf($format.' (%s)', array_merge($replacements, array($errorMsg))));
        }
    }
}
