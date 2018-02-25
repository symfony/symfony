<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

class Timer
{
    private $duration;

    public function __construct($duration)
    {
        $this->duration = $duration;
    }
}
