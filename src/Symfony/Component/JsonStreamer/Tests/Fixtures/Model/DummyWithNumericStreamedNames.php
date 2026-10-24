<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;

class DummyWithNumericStreamedNames
{
    #[StreamedName('0')]
    public int $first = 1;

    #[StreamedName('1')]
    public int $second = 2;

    #[StreamedName('12')]
    public string $third = 'three';
}
