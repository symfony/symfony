<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Session.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Drak <drak@zikula.org>
 */
class Session implements SessionInterface, \IteratorAggregate, \Countable
{
    /**
     * Storage driver.
     *
     * @var SessionStorageInterface
     */
    protected $storage;

    /**
     * @var string
     */
    private $flashName;

    /**
     * @var string
     */
    private $attributeName;

    /**
     * True if we have no previous session (No session cookie)
     * and nothing has been written to the session
     *
     * @var bool
     */
    private $emptySession = false;

    /**
     * Constructor.
     *
     * @param SessionStorageInterface $storage    A SessionStorageInterface instance
     * @param AttributeBagInterface   $attributes An AttributeBagInterface instance, (defaults null for default AttributeBag)
     * @param FlashBagInterface       $flashes    A FlashBagInterface instance (defaults null for default FlashBag)
     */
    public function __construct(SessionStorageInterface $storage = null, AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
    {
        $this->storage = $storage ?: new NativeSessionStorage();

        $attributes = $attributes ?: new AttributeBag();
        $this->attributeName = $attributes->getName();
        $this->registerBag($attributes);

        $flashes = $flashes ?: new FlashBag();
        $this->flashName = $flashes->getName();
        $this->registerBag($flashes);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsEmpty()
    {
        if (!$this->emptySession && !$this->isStarted()) {
            $this->emptySession = true;
        }
    }


    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->emptySession = false;

        return $this->storage->start();
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        if ($this->emptySession) {return null;}

        return $this->storage->getBag($this->attributeName)->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name, $default = null)
    {
        if ($this->emptySession) {return $default;}

        return $this->storage->getBag($this->attributeName)->get($name, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        $this->emptySession = false;
        $this->storage->getBag($this->attributeName)->set($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        if ($this->emptySession) {return array();}

        return $this->storage->getBag($this->attributeName)->all();
    }

    /**
     * {@inheritdoc}
     */
    public function replace(array $attributes)
    {
        $this->emptySession = false;
        $this->storage->getBag($this->attributeName)->replace($attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        if ($this->emptySession) {return null;}

        return $this->storage->getBag($this->attributeName)->remove($name);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if ($this->emptySession) {return null;}
        $this->storage->getBag($this->attributeName)->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->storage->isStarted();
    }

    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        if ($this->emptySession) {
            return new \ArrayIterator(array());
        }

        return new \ArrayIterator($this->storage->getBag($this->attributeName)->all());
    }

    /**
     * Returns the number of attributes.
     *
     * @return int The number of attributes
     */
    public function count()
    {
        if ($this->emptySession) {return 0;}

        return count($this->storage->getBag($this->attributeName)->all());
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate($lifetime = null)
    {
        $this->storage->clear();

        return $this->migrate(true, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function migrate($destroy = false, $lifetime = null)
    {
        return $this->storage->regenerate($destroy, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->storage->save();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->storage->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->storage->setId($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->storage->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->storage->setName($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataBag()
    {
        return $this->storage->getMetadataBag();
    }

    /**
     * {@inheritdoc}
     */
    public function registerBag(SessionBagInterface $bag)
    {
        $this->storage->registerBag($bag);
    }

    /**
     * {@inheritdoc}
     */
    public function getBag($name)
    {
        return $this->storage->getBag($name);
    }

    /**
     * Gets the flashbag interface.
     *
     * @return FlashBagInterface
     */
    public function getFlashBag()
    {
        return $this->getBag($this->flashName);
    }
}
