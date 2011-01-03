<?php

namespace Symfony\Component\HttpFoundation\SessionStorage;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ArraySessionStorage.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
