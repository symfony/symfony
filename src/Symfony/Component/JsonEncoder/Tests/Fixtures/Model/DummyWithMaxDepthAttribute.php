<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Attribute\MaxDepth;

class DummyWithMaxDepthAttribute
{
    #[MaxDepth(2, [self::class, 'boolean'])]
    public int $id = 1;

    public static function boolean(int $value): bool
    {
        return false;
    }
}
