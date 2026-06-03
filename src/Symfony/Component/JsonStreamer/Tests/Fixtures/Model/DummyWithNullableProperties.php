<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;

class DummyWithNullableProperties
{
    public ?string $name = null;
    public ?DummyBackedEnum $enum = null;
}
