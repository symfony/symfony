<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;

class DummyWithUnionProperties
{
    public DummyBackedEnum|string|null $value = DummyBackedEnum::ONE;
}
