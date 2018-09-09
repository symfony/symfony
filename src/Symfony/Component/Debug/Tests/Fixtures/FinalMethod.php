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
     */
    public function finalMethod2()
    {
    }

    public function anotherMethod()
    {
    }
}
