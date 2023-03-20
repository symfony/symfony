<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;

class DummyWithNullableProperties
{
    public ?string $name = null;
    public ?DummyBackedEnum $enum = null;
}
