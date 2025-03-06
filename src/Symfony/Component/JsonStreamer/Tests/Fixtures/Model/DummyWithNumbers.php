<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use BcMath\Number;

class DummyWithNumbers
{
    public \GMP $gmpNumber;
    public Number $bcMathNumber;
}
