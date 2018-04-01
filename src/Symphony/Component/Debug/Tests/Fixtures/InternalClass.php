<?php

namespace Symphony\Component\Debug\Tests\Fixtures;

/**
 * @internal
 */
class InternalClass
{
    use InternalTrait2;

    public function usedInInternalClass()
    {
    }
}
