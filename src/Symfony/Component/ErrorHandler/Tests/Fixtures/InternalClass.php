<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

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
