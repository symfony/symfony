<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Contracts\Service\ResetInterface;

class ClearableService implements ClearableInterface, ResetInterface
{
    public static $counter = 0;

    public function clear()
    {
        ++self::$counter;
    }

    public static function create()
    {
        return new self();
    }

    public function reset()
    {
        self::$counter = 0;
    }
}
