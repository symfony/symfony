<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Flash;

/**
 * FlashBag flash message container.
 *
 * @author Drak <drak@zikula.org>
 */
class FlashBag implements FlashBagInterface
{
    private $name = 'flashes';
    private $flashes = [];
    private $storageKey;

    /**
     * @param string $storageKey The key used to store flashes in the session
     */
    public function __construct(string $storageKey = '_symfony_flashes')
    {
        $this->storageKey = $storageKey;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function initialize(array &$flashes)
    {
        $this->flashes = &$flashes;
    }

    public function add(string $type, $message)
    {
        $this->flashes[$type][] = $message;
    }

    public function peek(string $type, array $default = [])
    {
        return $this->has($type) ? $this->flashes[$type] : $default;
    }

    public function peekAll()
    {
        return $this->flashes;
    }

    public function get(string $type, array $default = [])
    {
        if (!$this->has($type)) {
            return $default;
        }

        $return = $this->flashes[$type];

        unset($this->flashes[$type]);

        return $return;
    }

    public function all()
    {
        $return = $this->peekAll();
        $this->flashes = [];

        return $return;
    }

    public function set(string $type, $messages)
    {
        $this->flashes[$type] = (array) $messages;
    }

    public function setAll(array $messages)
    {
        $this->flashes = $messages;
    }

    public function has(string $type)
    {
        return \array_key_exists($type, $this->flashes) && $this->flashes[$type];
    }

    public function keys()
    {
        return array_keys($this->flashes);
    }

    public function getStorageKey()
    {
        return $this->storageKey;
    }

    public function clear()
    {
        return $this->all();
    }
}
