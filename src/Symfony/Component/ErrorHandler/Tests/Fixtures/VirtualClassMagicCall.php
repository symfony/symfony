<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @method string magicMethod()
 * @method static string staticMagicMethod()
 */
class VirtualClassMagicCall
{
    public static function __callStatic(string $name, array $arguments)
    {
    }

    public function __call(string $name, array $arguments)
    {
    }
}
