<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class ClearableService
{
    public static int $counter = 0;

    public function clear(): void
    {
        ++self::$counter;
    }
}
