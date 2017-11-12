<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class ClearableService
{
    public static $counter = 0;

    public function clear(): void
    {
        ++self::$counter;
    }
}
