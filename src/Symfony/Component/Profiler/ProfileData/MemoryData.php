<?php


namespace Symfony\Component\Profiler\ProfileData;


class MemoryData implements ProfileDataInterface
{

    protected $memory;

    protected $memoryLimit;

    public function __construct($memory, $memoryLimit)
    {
        $this->memory = $memory;
        $this->memoryLimit = $this->convertToBytes($memoryLimit);
    }

    /**
     * Gets the memory.
     *
     * @return int The memory
     */
    public function getMemory()
    {
        return $this->memory;
    }

    /**
     * Gets the PHP memory limit.
     *
     * @return int The memory limit
     */
    public function getMemoryLimit()
    {
        return $this->memoryLimit;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'memory';
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
}