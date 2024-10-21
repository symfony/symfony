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
     * @var bool
     */
    protected $started = false;

    /**
     * @var bool
     */
    protected $closed = false;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var MetadataBag
     */
    protected $metadataBag;

    /**
     * @var array|SessionBagInterface[]
     */
    protected $bags = [];

    public function __construct(string $name = 'MOCKSESSID', ?MetadataBag $metaBag = null)
    {
        $this->name = $name;
        $this->setMetadataBag($metaBag);
    }

    public function setSessionData(array $array)
    {
        $this->data = $array;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started) {
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
     */
    public function regenerate(bool $destroy = false, ?int $lifetime = null)
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
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setId(string $id)
    {
        if ($this->started) {
            throw new \LogicException('Cannot set session ID after the session has started.');
        }

        $this->id = $id;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        if (!$this->started || $this->closed) {
            throw new \RuntimeException('Trying to save a session that was not started yet or was already closed.');
        }
        // nothing to do since we don't persist the session data
        $this->closed = false;
        $this->started = false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        // clear out the bags
        foreach ($this->bags as $bag) {
            $bag->clear();
        }

        // clear out the session
        $this->data = [];

        // reconnect the bags to the session
        $this->loadSession();
    }

    /**
     * {@inheritdoc}
     */
    public function registerBag(SessionBagInterface $bag)
    {
        $this->bags[$bag->getName()] = $bag;
    }

    /**
     * {@inheritdoc}
     */
    public function getBag(string $name)
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(sprintf('The SessionBagInterface "%s" is not registered.', $name));
        }

        if (!$this->started) {
            $this->start();
        }

        return $this->bags[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->started;
    }

    public function setMetadataBag(?MetadataBag $bag = null)
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
     */
    public function getMetadataBag()
    {
        return $this->metadataBag;
    }

    /**
     * Generates a session ID.
     *
     * @return string
     */
    protected function generateId()
    {
        return bin2hex(random_bytes(16));
    }

    protected function loadSession()
    {
        $bags = array_merge($this->bags, [$this->metadataBag]);

        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $this->data[$key] = $this->data[$key] ?? [];
            $bag->initialize($this->data[$key]);
        }

        $this->started = true;
        $this->closed = false;
    }
}
