<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * Metadata container.
 *
 * Adds meta data to the session.
 *
 * @author Drak <drak@zikula.org>
 */
class MetaBag implements SessionBagInterface
{
    /**
     * @var string
     */
    private $name = '__meta';

    /**
     * @var string
     */
    private $storageKey;

    /**
     * @var array
     */
    protected $meta = array();

    /**
     * Unix timestamp.
     *
     * @var integer
     */
    private $lastUsed;

    /**
     * Constructor.
     *
     * @param string $storageKey The key used to store bag in the session.
     */
    public function __construct($storageKey = '_sf2_meta')
    {
        $this->storageKey = $storageKey;
        $this->meta = array('created' => 0, 'lastused' => 0, 'lifetime' => 0);
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array &$meta)
    {
        $this->meta = &$meta;

        if (isset($meta['created'])) {
            $this->lastUsed = $this->meta['lastused'];
            $this->meta['lastused'] = time();
        } else {
            $this->stampCreated();
        }
    }

    /**
     * Gets the lifetime that the session cookie was set with.
     *
     * @return integer
     */
    public function getLifetime()
    {
        return $this->meta['lifetime'];
    }

    /**
     * Stamps a new session's meta.
     *
     * @param integer $lifetime Sets the cookie lifetime for the session cookie. A null value
     *                          will leave the system settings unchanged, 0 sets the cookie
     *                          to expire with browser session. Time is in seconds, and is
     *                          not a Unix timestamp.
     */
    public function stampNew($lifetime = null)
    {
        $this->stampCreated($lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }

    /**
     * Gets the created timestamp meta data.
     *
     * @return integer Unix timestamp
     */
    public function getCreated()
    {
        return $this->meta['created'];
    }

    /**
     * Gets the last used meta data.
     *
     * @return integer Unix timestamp
     */
    public function getLastUsed()
    {
        return $this->lastUsed;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        // nothing to do
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets name.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    private function stampCreated($lifetime = null)
    {
        $timeStamp = time();
        $this->meta['created'] = $this->meta['lastused'] = $this->lastUsed = $timeStamp;
        $this->meta['lifetime'] = (null === $lifetime) ? ini_get('session.cookie_lifetime') : $lifetime;
    }
}
