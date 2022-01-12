<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class Foo
{
    public static function createBar()
    {
        return new Bar(new \stdClass());
    }

    public static function createBarArguments(\stdClass $stdClass, \stdClass $stdClassOptional = null)
    {
        return new Bar($stdClass);
    }

    public static function createCallable(): callable
    {
        return function () {};
    }

    public static function createArray(): array
    {
        return [];
    }

    public static function createStdClass(): \stdClass
    {
        return new \stdClass();
    }
}
