<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class ExtendedFinalMethod extends FinalMethod
{
    /**
     * {@inheritdoc}
     */
    public function finalMethod()
    {
    }

    public function anotherMethod()
    {
    }
}
