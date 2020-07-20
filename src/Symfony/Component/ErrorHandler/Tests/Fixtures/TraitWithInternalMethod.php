<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

trait TraitWithInternalMethod
{
    /**
     * @internal
     */
    public function foo()
    {
    }
}
