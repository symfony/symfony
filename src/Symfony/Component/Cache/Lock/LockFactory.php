<?php

namespace Symfony\Component\Cache\Lock;

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
     * @param string[] $keys
     *
     * @return Lock
     */
    public function create(array $keys)
    {
        $lock = new Lock($this->timeout, $this->sleep);
        $id = md5(uniqid(mt_rand(), true));

        foreach ($keys as $key) {
            $lock->add($key, new KeyLock(sprintf($this->pattern, $key), $id));
        }

        return $lock;
    }
}
