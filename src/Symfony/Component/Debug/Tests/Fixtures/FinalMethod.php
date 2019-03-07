<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class FinalMethod
{
    /**
     * @final since version 3.3.
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
