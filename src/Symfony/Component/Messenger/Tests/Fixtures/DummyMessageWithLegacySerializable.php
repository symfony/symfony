<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

class DummyMessageWithLegacySerializable
{
    public function __construct(public DummyLegacySerializable $value)
    {
    }
}
