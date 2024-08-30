<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

interface ReturnTypeInterface
{
    /**
     * @return string
     */
    public function returnTypeInterface();

    /**
     * @return-typehint-will-change
     * @return string
     */
    public function returnTypeInterfaceWithWillChange();

    /**
     * @return-typehint-will-change 42.0.0
     * @return string
     */
    public function returnTypeInterfaceWithWillChangeAndVersion();
}
