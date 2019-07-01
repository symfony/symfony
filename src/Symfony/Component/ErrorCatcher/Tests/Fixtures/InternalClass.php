<?php

namespace Symfony\Component\ErrorCatcher\Tests\Fixtures;

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
