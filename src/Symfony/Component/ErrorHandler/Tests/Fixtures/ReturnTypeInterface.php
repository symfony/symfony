<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

interface ReturnTypeInterface
{
    const BAR = 'bar';

    /**
     * @return string
     */
    public function returnTypeInterface();

    /**
     * @return self::BAR
     */
    public function interfaceClassConstant();
}
