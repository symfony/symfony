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
 * ArraySessionStorage mocks the session for unit tests.
 *
 * When doing functional testing, you should use FilesystemSessionStorage instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */

class ArraySessionStorage implements SessionStorageInterface
{
    private $data = array();

    public function read($key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function regenerate($destroy = false)
    {
        if ($destroy) {
            $this->data = array();
        }

        return true;
    }

    public function remove($key)
    {
        unset($this->data[$key]);
    }

    public function start()
    {
    }

    public function getId()
    {
    }

    public function write($key, $data)
    {
        $this->data[$key] = $data;
    }
}
