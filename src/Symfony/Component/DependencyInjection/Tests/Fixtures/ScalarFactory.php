<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

final class ScalarFactory
{
    public static function getSomeValue(): string
    {
        return 'some value';
    }
}
