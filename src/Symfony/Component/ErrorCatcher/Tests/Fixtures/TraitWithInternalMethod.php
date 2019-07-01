<?php

namespace Symfony\Component\ErrorCatcher\Tests\Fixtures;

trait TraitWithInternalMethod
{
    /**
     * @internal
     */
    public function foo()
    {
    }
}
