<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

interface ReturnTypeInterface
{
    /**
     * @return string
     */
    public function returnTypeInterface();

    /**
     * @return-type-will-change
     * @return string
     */
    public function returnTypeInterfaceWithWillChange();

    /**
     * @return-type-will-change in version 42.0.0
     * @return string
     */
    public function returnTypeInterfaceWithWillChangeAndVersion();
}
