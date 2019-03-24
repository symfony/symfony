<?php

namespace Symfony\Component\Kernel\Tests\Fixtures;

class ResettableService
{
    public static $counter = 0;

    public function reset()
    {
        ++self::$counter;
    }
}
