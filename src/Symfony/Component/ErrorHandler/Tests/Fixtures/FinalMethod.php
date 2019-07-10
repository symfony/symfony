<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

class FinalMethod
{
    /**
     * @final
     */
    public function finalMethod()
    {
    }

    /**
     * @final
     *
     * @return int
     */
    public function finalMethod2()
    {
    }

    public function anotherMethod()
    {
    }
}
