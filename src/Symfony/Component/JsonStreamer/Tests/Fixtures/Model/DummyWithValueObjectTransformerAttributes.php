<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\JsonStreamer\Transformer\DateTimeValueObjectTransformer;

class DummyWithValueObjectTransformerAttributes
{
    #[ValueTransformer(
        nativeToStream: DateTimeValueObjectTransformer::class,
        streamToNative: DateTimeValueObjectTransformer::class,
    )]
    public int $id = 1;
}
