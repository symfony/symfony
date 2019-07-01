<?php

namespace Symfony\Component\ErrorCatcher\Tests\Fixtures;

class ExtendedFinalMethod extends FinalMethod
{
    use FinalMethod2Trait;

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
