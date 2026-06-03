<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\includes;

use Symfony\Component\DependencyInjection\Tests\Compiler\A;
use Symfony\Component\DependencyInjection\Tests\Compiler\Lille;

class MultipleArgumentsOptionalScalarNotReallyOptional
{
    public function __construct(A $a, $foo = 'default_val', Lille $lille)
    {
    }
}
