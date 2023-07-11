<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class MultiResettableService
{
    public static int $resetFirstCounter = 0;
    public static int $resetSecondCounter = 0;

    public function resetFirst(): void
    {
        ++self::$resetFirstCounter;
    }

    public function resetSecond(): void
    {
        ++self::$resetSecondCounter;
    }
}
