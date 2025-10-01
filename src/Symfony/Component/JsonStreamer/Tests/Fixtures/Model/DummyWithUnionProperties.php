<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;

class DummyWithUnionProperties
{
    public DummyBackedEnum|string|null $value = DummyBackedEnum::ONE;
}
