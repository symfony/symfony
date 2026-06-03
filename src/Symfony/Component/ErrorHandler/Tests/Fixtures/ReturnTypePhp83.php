<?php

namespace Test\Symfony\Component\ErrorHandler\Tests;

use Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParentPhp83;

class ReturnTypePhp83 extends ReturnTypeParentPhp83
{
    public function classConstantWithType() { }
    public function classConstantWithUnionType() { }
}
