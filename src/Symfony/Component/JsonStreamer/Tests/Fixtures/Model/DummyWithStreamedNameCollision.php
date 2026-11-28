<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;

class DummyWithStreamedNameCollision
{
    public int $id = 1;

    #[StreamedName('id')]
    public string $name = 'dummy';
}
