<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

/**
 * @internal
 */
trait InternalTrait2
{
    /**
     * @internal since version 3.4
     */
    public function internalMethod()
    {
    }

    /**
     * @internal but should not trigger a deprecation.
     */
    public function usedInInternalClass()
    {
    }
}
