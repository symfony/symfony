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
use Symfony\Component\HttpFoundation\Session\EmptyBag;

/**
 * This is a wrapper class for the session storage when it is empty
 *
 * @author Terje Bråten <terje@braten.be>
 */
class EmptyStorage implements EmptyStorageInterface
{
    /**
     * The real storage that this class is a wrapper for
     *
     * @var SessionStorageInterface
     */
    protected $realStorage;

    /**
     * @var Boolean
     */
    protected $isEmpty = true;

    /**
     * Array of SessionBagInterface
     *
     * @var SessionBagInterface[]
     */
    protected $bags;

    /**
     * Constructor.
     *
     * @param SessionStorageInterface $realStorage The real stoarge object
     */
    public function __construct(SessionStorageInterface $realStorage)
    {
        $this->realStorage = $realStorage;
        $this->isEmpty = true;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->isEmpty = false;

        return $this->realStorage->start();
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        if ($this->isEmpty) {
            return false;
        }

        return $this->realStorage->isStarted();
    }

    /**
     * {@inheritdoc}
     */
    public function wasStarted()
    {
        return $this->realStorage->wasStarted();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->realStorage->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->realStorage->setId($id);
        if ($this->isEmpty && $this->realStorage->wasStarted()) {
            $this->isEmpty = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->realStorage->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->realStorage->setName($name);
        if ($this->isEmpty && $this->realStorage->wasStarted()) {
            $this->isEmpty = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false, $lifetime = null)
    {
        return $this->realStorage->regenerate($destroy, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->realStorage->save();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if ($this->isEmpty) {
            return;
        }

        $this->realStorage->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function registerBag(SessionBagInterface $bag)
    {
        $this->realStorage->registerBag($bag);
        $this->bags[$bag->getName()] = $bag;
    }

    /**
     * {@inheritdoc}
     */
    public function getBag($name)
    {
        if (!$this->isEmpty) {
            return $this->realStorage->getBag($name);
        }

        if (!array_key_exists($name, $this->bags)) {
            throw new \InvalidArgumentException(sprintf('The SessionBagInterface %s is not registered.', $name));
        }

        return EmptyBag::create($this, $this->bags[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getRealBag($name)
    {
        $this->isEmpty = false;

        return $this->realStorage->getBag($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataBag()
    {
        return $this->realStorage->getMetadataBag();
    }
}
