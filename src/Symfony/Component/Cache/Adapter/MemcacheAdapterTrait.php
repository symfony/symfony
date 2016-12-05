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

/**
 * @author Rob Frawley 2nd <rmf@src.run>
 *
 * @internal
 */
trait MemcacheAdapterTrait
{
    private static $defaultClientServerValues = array(
        'host' => '127.0.0.1',
        'port' => 11211,
        'weight' => 100,
    );

    /**
     * @var \Memcache|\Memcached
     */
    private $client;

    /**
     * Provide ability to reconfigure adapter after construction. See {@see create()} for acceptable DSN formats.
     *
     * @param string[] $dsns
     * @param mixed[]  $opts
     *
     * @return bool
     */
    public function setup(array $dsns = array(), array $opts = array())
    {
        $return = true;

        foreach ($opts as $opt => $val) {
            $return = $this->setOption($opt, $val) && $return;
        }
        foreach ($dsns as $dsn) {
            $return = $this->addServer($dsn) && $return;
        }

        return $return;
    }

    /**
     * Returns the Memcache client instance.
     *
     * @return \Memcache|\Memcached
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        if (!isset($namespace[0]) || false === $ids = $this->getIdsByPrefix($namespace)) {
            return $this->client->flush();
        }

        $return = true;

        do {
            $return = $this->doDelete($ids) && $return;
        } while ($ids = $this->getIdsByPrefix($namespace));

        return $return;
    }

    private function dsnExtract($dsn)
    {
        $scheme = false !== strpos(static::class, 'Memcached') ? 'memcached' : 'memcache';

        if (false === ($srv = parse_url($dsn)) || $srv['scheme'] !== $scheme || count($srv) > 4) {
            throw new InvalidArgumentException(sprintf('Invalid %s DSN: %s (expects "%s://example.com[:1234][?weight=<int>]")', $scheme, $dsn, $scheme));
        }

        if (isset($srv['query']) && 1 === preg_match('{weight=([^&]{1,})}', $srv['query'], $weight)) {
            $srv['weight'] = (int) $weight[1];
        }

        return $this->dsnSanitize($srv, $scheme);
    }

    private function dsnSanitize(array $srv, $scheme)
    {
        $srv += self::$defaultClientServerValues;

        if (false === ($host = filter_var($srv['host'], FILTER_VALIDATE_IP)) ||
            false === ($host = filter_var($srv['host'], FILTER_SANITIZE_URL))) {
            throw new InvalidArgumentException(sprintf('Invalid %s DSN host: %s (expects resolvable IP or hostname)', $scheme, $srv['host']));
        }

        if (false === ($weight = filter_var($srv['weight'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100))))) {
            throw new InvalidArgumentException(sprintf('Invalid %s DSN weight: %s (expects int >=1 and <= 100)', $scheme, $srv['weight']));
        }

        return array($host, $srv['port'], $weight);
    }
}
