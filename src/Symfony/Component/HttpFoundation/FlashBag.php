<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * FlashBag flash message container.
 *
 * @author Drak <drak@zikula.org>
 */
class FlashBag implements FlashBagInterface
{
    /**
     * Flash messages.
     *
     * @var array
     */
    private $flashes = array();

    /**
     * The storage key for flashes in the session
     *
     * @var string
     */
    private $storageKey;

    /**
     * Constructor.
     *
     * @param type $storageKey The key used to store flashes in the session.
     */
    public function __construct($storageKey = '_sf2_flashes')
    {
        $this->storageKey = $storageKey;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array &$flashes)
    {
        $this->flashes = &$flashes;
    }

    /**
     * {@inheritdoc}
     */
    public function add($message, $type = self::NOTICE)
    {
        $this->flashes[$type][] = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function get($type)
    {
        if (!$this->has($type)) {
            return array();
        }

        return $this->flashes[$type];
    }

    /**
     * {@inheritdoc}
     */
    public function pop($type)
    {
        if (!$this->has($type)) {
            return array();
        }

        return $this->clear($type);
    }

    /**
     * {@inheritdoc}
     */
    public function popAll()
    {
        return $this->clearAll();
    }

    /**
     * {@inheritdoc}
     */
    public function set($type, array $array)
    {
        $this->flashes[$type] = $array;
    }

    /**
     * {@inheritdoc}
     */
    public function has($type)
    {
        return array_key_exists($type, $this->flashes);
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        return array_keys($this->flashes);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->flashes;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($type)
    {
        $return = array();
        if (isset($this->flashes[$type])) {
            $return = $this->flashes[$type];
            unset($this->flashes[$type]);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function clearAll()
    {
        $return = $this->flashes;
        $this->flashes = array();

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }
}
