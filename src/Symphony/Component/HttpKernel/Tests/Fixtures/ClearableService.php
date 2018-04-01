<?php

namespace Symphony\Component\HttpKernel\Tests\Fixtures;

class ClearableService
{
    public static $counter = 0;

    public function clear()
    {
        ++self::$counter;
    }
}
