<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;

class DummyWithDollarNamedProperties
{
    #[StreamedName('$foo')]
    public bool $foo = true;

    #[StreamedName('{$foo->bar}')]
    public bool $bar = true;
}
