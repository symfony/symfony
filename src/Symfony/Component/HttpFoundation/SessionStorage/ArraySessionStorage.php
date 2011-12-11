<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\AttributeBagInterface;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * ArraySessionStorage mocks the session for unit tests.
 *
 * When doing functional testing, you should use FilesystemSessionStorage instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Drak <drak@zikula.org>
 */
class ArraySessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $sessionId;

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var array
     */
    private $flashes = array();

    /**
     * Constructor.
     *
     * There is no option to set session options here because this object does not start the PHP session.
     * This constructor is for easy testing, simply use `$storage = new AttributeBag()` unless you require
     * specific implementations of Bag interfaces.
     *
     * @param AttributeBagInterface $attributes AttributeBagInterface, defaults to null for AttributeBag default
     * @param FlashBagInterface     $flashes    FlashBagInterface, defaults to null for FlashBag default
     */
    public function __construct(AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
    {
        parent::__construct($attributes, $flashes);
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->attributeBag->initialize($this->attributes);
        $this->flashBag->initialize($this->flashes);
        $this->sessionId = $this->generateSessionId();
        session_id($this->sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false)
    {
        if (!$this->started) {
            $this->start();
        }

        if ($destroy) {
            $this->attributeBag->clear();
            $this->flashBag->clearAll();
        }

        $this->sessionId = $this->generateSessionId();
        session_id($this->sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        if (!$this->started) {
            return '';
        }

        return $this->sessionId;
    }
}