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
 * AutoExpireFlashBag flash message container.
 *
 * @author Drak <drak@zikula.org>
 */
class AutoExpireFlashBag implements FlashBagInterface
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
        $this->flashes = array('display' => array(), 'new' => array());
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array &$flashes)
    {
        $this->flashes = &$flashes;

        // The logic: messages from the last request will be stored in new, so we move them to previous
        // This request we will show what is in 'display'.  What is placed into 'new' this time round will
        // be moved to display next time round.
        $this->flashes['display'] = array_key_exists('new', $this->flashes) ? $this->flashes['new'] : array();
        $this->flashes['new'] = array();
    }

    /**
     * {@inheritdoc}
     */
    public function add($message, $type = self::NOTICE)
    {
        $this->flashes['new'][$type][] = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function get($type)
    {
        if (!$this->has($type)) {
            return array();
        }

        return $this->flashes['display'][$type];
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
        $this->flashes['new'][$type] = $array;
    }

    /**
     * {@inheritdoc}
     */
    public function has($type)
    {
        return array_key_exists($type, $this->flashes['display']);
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        return array_keys($this->flashes['display']);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return array_key_exists('display', $this->flashes) ? (array)$this->flashes['display'] : array();
    }

    /**
     * {@inheritdoc}
     */
    public function clear($type)
    {
        $return = array();
        if (isset($this->flashes['new'][$type])) {
            unset($this->flashes['new'][$type]);
        }

        if (isset($this->flashes['display'][$type])) {
            $return = $this->flashes['display'][$type];
            unset($this->flashes['display'][$type]);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function clearAll()
    {
        $return = $this->flashes['display'];
        $this->flashes = array('new' => array(), 'display' => array());

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
