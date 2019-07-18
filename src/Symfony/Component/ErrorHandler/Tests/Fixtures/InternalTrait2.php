<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @internal
 */
trait InternalTrait2
{
    /**
     * @internal
     */
    public function internalMethod()
    {
    }

    /**
     * @internal but should not trigger a deprecation
     */
    public function usedInInternalClass()
    {
    }
}
