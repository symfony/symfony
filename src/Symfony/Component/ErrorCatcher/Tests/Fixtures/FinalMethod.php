<?php

namespace Symfony\Component\ErrorCatcher\Tests\Fixtures;

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
