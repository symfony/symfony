<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class JsonSerializableDummy implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['serialized' => true];
    }
}
