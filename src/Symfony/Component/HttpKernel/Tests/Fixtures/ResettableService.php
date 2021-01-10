<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\HttpKernel\Attribute\Reset;

#[Reset(method: 'reset')]
class ResettableService
{
    public static $counter = 0;

    public function reset()
    {
        ++self::$counter;
    }
}
