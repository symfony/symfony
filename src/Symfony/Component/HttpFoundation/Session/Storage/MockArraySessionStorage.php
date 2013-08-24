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
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;

/**
 * MockArraySessionStorage mocks the session for unit tests.
 *
 * No PHP session is actually started since a session can be initialized
 * and shutdown only once per PHP execution cycle.
 *
 * When doing functional testing, you should use MockFileSessionStorage instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Drak <drak@zikula.org>
 *
 * @since v2.1.0
 */
class MockArraySessionStorage implements SessionStorageInterface
{
    /**
     * @var string
     */
    protected $id = '';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var boolean
     */
    protected $started = false;

    /**
     * @var boolean
     */
    protected $closed = false;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var MetadataBag
     */
    protected $metadataBag;

    /**
     * @var array
     */
    protected $bags;

    /**
     * Constructor.
     *
     * @param string      $name    Session name
     * @param MetadataBag $metaBag MetadataBag instance.
     *
     * @since v2.3.0
     */
    public function __construct($name = 'MOCKSESSID', MetadataBag $metaBag = null)
    {
        $this->name = $name;
        $this->setMetadataBag($metaBag);
    }

    /**
     * Sets the session data.
     *
     * @param array $array
     *
     * @since v2.1.0
     */
    public function setSessionData(array $array)
    {
        $this->data = $array;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function start()
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        if (empty($this->id)) {
            $this->id = $this->generateId();
        }

        $this->loadSession();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function regenerate($destroy = false, $lifetime = null)
    {
        if (!$this->started) {
            $this->start();
        }

        $this->metadataBag->stampNew($lifetime);
        $this->id = $this->generateId();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function setId($id)
    {
        if ($this->started) {
            throw new \LogicException('Cannot set session ID after the session has started.');
        }

        $this->id = $id;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function save()
    {
        if (!$this->started || $this->closed) {
            throw new \RuntimeException("Trying to save a session that was not started yet or was already closed");
        }
        // nothing to do since we don't persist the session data
        $this->closed = false;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function clear()
    {
        // clear out the bags
        foreach ($this->bags as $bag) {
            $bag->clear();
        }

        // clear out the session
        $this->data = array();

        // reconnect the bags to the session
        $this->loadSession();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function registerBag(SessionBagInterface $bag)
    {
        $this->bags[$bag->getName()] = $bag;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getBag($name)
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(sprintf('The SessionBagInterface %s is not registered.', $name));
        }

        if (!$this->started) {
            $this->start();
        }

        return $this->bags[$name];
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Sets the MetadataBag.
     *
     * @param MetadataBag $bag
     *
     * @since v2.1.0
     */
    public function setMetadataBag(MetadataBag $bag = null)
    {
        if (null === $bag) {
            $bag = new MetadataBag();
        }

        $this->metadataBag = $bag;
    }

    /**
     * Gets the MetadataBag.
     *
     * @return MetadataBag
     *
     * @since v2.1.0
     */
    public function getMetadataBag()
    {
        return $this->metadataBag;
    }

    /**
     * Generates a session ID.
     *
     * This doesn't need to be particularly cryptographically secure since this is just
     * a mock.
     *
     * @return string
     *
     * @since v2.1.0
     */
    protected function generateId()
    {
        return sha1(uniqid(mt_rand()));
    }

    /**
     * @since v2.1.0
     */
    protected function loadSession()
    {
        $bags = array_merge($this->bags, array($this->metadataBag));

        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $this->data[$key] = isset($this->data[$key]) ? $this->data[$key] : array();
            $bag->initialize($this->data[$key]);
        }

        $this->started = true;
        $this->closed = false;
    }
}
