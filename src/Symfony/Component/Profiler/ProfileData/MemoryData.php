<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\ProfileData;

/**
 * MemoryData.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class MemoryData implements ProfileDataInterface
{
    private $memory;
    private $memoryLimit;

    /**
     * Constructor.
     *
     * @param int $memory      The current used memory.
     * @param int $memoryLimit The memory limit.
     */
    public function __construct($memory, $memoryLimit)
    {
        $this->memory = $memory;
        $this->memoryLimit = $this->convertToBytes($memoryLimit);
    }

    /**
     * Returns the memory.
     *
     * @return int The memory
     */
    public function getMemory()
    {
        return $this->memory;
    }

    /**
     * Returns the PHP memory limit.
     *
     * @return int The memory limit
     */
    public function getMemoryLimit()
    {
        return $this->memoryLimit;
    }

    private function convertToBytes($memoryLimit)
    {
        if ('-1' === $memoryLimit) {
            return -1;
        }

        $memoryLimit = strtolower($memoryLimit);
        $max = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($memoryLimit, -1)) {
            case 't': $max *= 1024;
            case 'g': $max *= 1024;
            case 'm': $max *= 1024;
            case 'k': $max *= 1024;
        }

        return $max;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array('memory' => $this->memory, 'memoryLimit' => $this->memoryLimit));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->memory = $data['memory'];
        $this->memoryLimit = $data['memoryLimit'];
    }

    public function getName()
    {
        return 'memory';
    }
}
