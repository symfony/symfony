<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

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
