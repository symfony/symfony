<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\HttpKernel\Attribute\Reset;

#[Reset(method: 'clear')]
class ClearableService
{
    public static $counter = 0;

    public function clear()
    {
        ++self::$counter;
    }
}
