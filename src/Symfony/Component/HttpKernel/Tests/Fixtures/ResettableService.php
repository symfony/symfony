<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class ResettableService
{
    public static int $counter = 0;

    public function reset(): void
    {
        ++self::$counter;
    }
}
