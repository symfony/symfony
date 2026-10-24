<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;

class DummyWithNumericStreamedObject
{
    #[StreamedName('0')]
    public ClassicDummy $dummy;
}
