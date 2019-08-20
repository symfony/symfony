<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

final class ScalarFactory
{
    /**
     * @return string
     */
    public static function getSomeValue(): string
    {
        return 'some value';
    }
}
