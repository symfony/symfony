<?php

namespace Test\Symfony\Component\ErrorHandler\Tests\Fixtures;

interface OutsideInterface
{
    /**
     * @return string - should not be reported as it's in a non-Symfony namespace
     */
    public function outsideMethod();
}
