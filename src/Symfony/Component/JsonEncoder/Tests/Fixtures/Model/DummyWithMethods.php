<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

class DummyWithMethods
{
    public int $id = 1;

    public function nonStatic(int $value): string
    {
        return (string) (3 * $value);
    }
}
