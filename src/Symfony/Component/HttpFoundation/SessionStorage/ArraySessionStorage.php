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

/**
 * ArraySessionStorage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class ArraySessionStorage implements SessionStorageInterface
{
    private $data = array();

    /**
     * {@inheritDoc}
     */
    public function start()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function read($key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * {@inheritDoc}
     */
    public function remove($key)
    {
        unset($this->data[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function write($key, $data)
    {
        $this->data[$key] = $data;
    }

    /**
     * {@inheritDoc}
     */
    public function regenerate($destroy = false)
    {
        if ($destroy) {
            $this->data = array();
        }

        return true;
    }
}
