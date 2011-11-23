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

use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * ArraySessionStorage mocks the session for unit tests.
 *
 * When doing functional testing, you should use FilesystemSessionStorage instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class ArraySessionStorage extends AbstractSessionStorage
{
    /**
     * Storage data.
     * 
     * @var array
     */
    private $data = array();

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false)
    {
        if ($destroy) {
            $this->clear();
            $this->flashBag->clearAll();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->attributes = array();
        
        $flashes = array();
        $this->flashBag->initialize($flashes);
        $this->started = true;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
    }
}
