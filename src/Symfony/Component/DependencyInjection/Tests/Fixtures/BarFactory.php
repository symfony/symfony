<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class BarFactory
{
    /**
     * @var iterable
     */
    private $bars;

    public function __construct(iterable $bars)
    {
        $this->bars = iterator_to_array($bars);
    }

    public function getDefaultBar(): BarInterface
    {
        return reset($this->bars);
    }
}
