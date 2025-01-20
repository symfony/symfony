<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

abstract class ReturnTypeParentPhp83
{
    const string FOO = 'foo';
    const string|int BAR = 'bar';

    /**
     * @return self::FOO
     */
    public function classConstantWithType()
    {
    }

    /**
     * @return self::BAR
     */
    public function classConstantWithUnionType()
    {
    }
}
