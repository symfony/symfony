<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

class DummyLegacySerializable implements \Serializable
{
    public function __construct(private string $value = '')
    {
    }

    public function serialize(): string
    {
        return $this->value;
    }

    public function unserialize($data): void
    {
        $this->value = $data;
    }
}
