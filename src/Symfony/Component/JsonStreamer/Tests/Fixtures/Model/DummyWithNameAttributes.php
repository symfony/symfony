<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;

class DummyWithNameAttributes
{
    #[StreamedName('@id')]
    public int $id = 1;

    public string $name = 'dummy';
}
