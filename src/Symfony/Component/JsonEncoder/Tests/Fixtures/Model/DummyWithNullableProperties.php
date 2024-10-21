<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;

class DummyWithNullableProperties
{
    public ?string $name = null;
    public ?DummyBackedEnum $enum = null;
}
