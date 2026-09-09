<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\Attribute\StreamedName;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueObject\Height;

#[JsonStreamable]
class StreamableDummy
{
    #[StreamedName('@id')]
    public int $id = 1;

    public ?string $name = null;

    public Height $height;
}
