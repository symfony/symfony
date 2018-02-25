<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

class TimerCollection implements \IteratorAggregate
{
    /**
     * @var array
     */
    private $timers;

    /**
     * @param Timer[] $timers
     */
    public function __construct(array $timers)
    {
        $this->timers = $timers;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->timers);
    }
}
