<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class MultiResettableService
{
    public static $resetFirstCounter = 0;
    public static $resetSecondCounter = 0;

    public function resetFirst()
    {
        ++self::$resetFirstCounter;
    }

    public function resetSecond()
    {
        ++self::$resetSecondCounter;
    }
}
