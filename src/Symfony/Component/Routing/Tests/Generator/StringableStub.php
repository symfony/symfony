<?php

namespace Symfony\Component\Routing\Tests\Generator;

class StringableStub
{
    public function __toString()
    {
        return 'dummy';
    }
}
