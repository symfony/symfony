<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Attribute\EncodedName;

class DummyWithNameAttributes
{
    #[EncodedName('@id')]
    public int $id = 1;

    public string $name = 'dummy';
}
