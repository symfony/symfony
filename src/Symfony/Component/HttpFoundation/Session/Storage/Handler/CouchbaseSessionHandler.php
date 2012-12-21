<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

/**
 * CouchbaseSessionHandler.
 *
 * Couchbase based esession storage handler based on the Couchbase class
 * provided by the PHP couchbase extension.
 *
 * @see http://www.couchbase.com/develop/php/current
 *
 * @author Michael Nitschinger <michael@nitschinger.at>
 */
class CouchbaseSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var \Couchbase Couchbase driver.
     */
    private $couchbase;

    /**
     * @var integer Time to live in seconds
     */
    private $ttl;

    /**
     * @var string Key prefix for shared environments.
     */
    private $prefix;

    /**
     * Constructor.
     *
     * List of available options:
     *  * prefix: The prefix to use for the couchbase keys in order to avoid collision
     *  * expiretime: The time to live in seconds
     *
     * @param \Couchbase $couchbase A \Couchbase instance
     * @param array      $options   An associative array of Couchbase options
     *
     * @throws \InvalidArgumentException When unsupported options are passed
     */
    public function __construct(\Couchbase $couchbase, array $options = array())
    {
        $this->couchbase = $couchbase;

        if ($diff = array_diff(array_keys($options), array('prefix', 'expiretime'))) {
            throw new \InvalidArgumentException(sprintf(
                'The following options are not supported "%s"', implode(', ', $diff)
            ));
        }

        $this->ttl = isset($options['expiretime']) ? (int) $options['expiretime'] : 86400;
        $this->prefix = isset($options['prefix']) ? $options['prefix'] : 'sf2s';
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId)
    {
        return $this->couchbase->get($this->prefix.$sessionId) ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data)
    {
        return $this->couchbase->set($this->prefix.$sessionId, $data, time() + $this->ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId)
    {
        return $this->couchbase->delete($this->prefix.$sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime)
    {
        // not required here because couchbase will auto expire the records anyhow.
        return true;
    }
}
