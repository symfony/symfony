<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Extension\Lock;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class LockFactory
{
    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $sleep;

    /**
     * @var string
     */
    private $pattern;

    /**
     * @param int    $timeout
     * @param int    $sleep
     * @param string $pattern
     */
    public function __construct($timeout, $sleep, $pattern = '%s.__lock__')
    {
        $this->timeout = $timeout;
        $this->sleep = $sleep;
        $this->pattern = $pattern;
    }

    /**
     * @param string[]    $keys
     * @param string|null $id
     *
     * @return Lock
     */
    public function create(array $keys, $id = null)
    {
        $lock = new Lock($this->timeout, $this->sleep);
        $id = $id ?: md5(uniqid(mt_rand(), true));

        foreach ($keys as $key) {
            $lock->add($key, new KeyLock(sprintf($this->pattern, $key), $id));
        }

        return $lock;
    }
}
