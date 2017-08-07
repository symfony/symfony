<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

/**
 * @internal since version 3.4.
 */
class InternalClass
{
    use InternalTrait2;

    public function usedInInternalClass()
    {
    }
}
