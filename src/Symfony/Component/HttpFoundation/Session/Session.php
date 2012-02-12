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
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * Session.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class Session implements SessionInterface
{
    /**
     * Storage driver.
     *
     * @var SessionStorageInterface
     */
    protected $storage;

    /**
     * Constructor.
     *
     * @param SessionStorageInterface $storage A SessionStorageInterface instance.
     * @param AttributeBagInterface   $attributes An AttributeBagInterface instance, (defaults null for default AttributeBag)
     * @param FlashBagInterface       $flashes    A FlashBagInterface instance (defaults null for default FlashBag)
     */
    public function __construct(SessionStorageInterface $storage, AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
    {
        $this->storage = $storage;
        $this->registerBag($attributes ?: new AttributeBag());
        $this->registerBag($flashes ?: new FlashBag());
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return $this->storage->start();
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return $this->storage->getBag('attributes')->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name, $default = null)
    {
        return $this->storage->getBag('attributes')->get($name, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        $this->storage->getBag('attributes')->set($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->storage->getBag('attributes')->all();
    }

    /**
     * {@inheritdoc}
     */
    public function replace(array $attributes)
    {
        $this->storage->getBag('attributes')->replace($attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        return $this->storage->getBag('attributes')->remove($name);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->storage->getBag('attributes')->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate()
    {
        $this->storage->clear();

        return $this->storage->regenerate(true);
    }

    /**
     * {@inheritdoc}
     */
    public function migrate($destroy = false)
    {
        return $this->storage->regenerate($destroy);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->storage->save();
    }

    /**
     * Returns the session ID
     *
     * @return mixed The session ID
     *
     * @api
     */
    public function getId()
    {
        return $this->storage->getId();
    }

    /**
     * Registers a SessionBagInterface with the sessio.
     *
     * @param SessionBagInterface $bag
     */
    public function registerBag(SessionBagInterface $bag)
    {
        $this->storage->registerBag($bag);
    }

    /**
     * Get's a bag instance.
     *
     * @param string $name
     *
     * @return SessionBagInterface
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
        return $this->getBag('flashes');
    }

    // the following methods are kept for compatibility with Symfony 2.0 (they will be removed for Symfony 2.3)

    /**
     * @return array
     *
     * @deprecated since 2.1, will be removed from 2.3
     */
    public function getFlashes()
    {
        return $this->getBag('flashes')->all();
    }

    /**
     * @param array $values
     *
     * @deprecated since 2.1, will be removed from 2.3
     */
    public function setFlashes($values)
    {
       $this->getBag('flashes')->setAll($values);
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     *
     * @deprecated since 2.1, will be removed from 2.3
     */
    public function getFlash($name, $default = null)
    {
       return $this->getBag('flashes')->get($name, $default);
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @deprecated since 2.1, will be removed from 2.3
     */
    public function setFlash($name, $value)
    {
       $this->getBag('flashes')->set($name, $value);
    }

    /**
     * @param string $name
     *
     * @return Boolean
     *
     * @deprecated since 2.1, will be removed from 2.3
     */
    public function hasFlash($name)
    {
       return $this->getBag('flashes')->has($name);
    }

    /**
     * @param string $name
     *
     * @deprecated since 2.1, will be removed from 2.3
     */
    public function removeFlash($name)
    {
       $this->getBag('flashes')->get($name);
    }

    /**
     * @return array
     *
     * @deprecated since 2.1, will be removed from 2.3
     */
    public function clearFlashes()
    {
       return $this->getBag('flashes')->clear();
    }
}
