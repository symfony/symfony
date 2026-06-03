<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithMethods
{
    public int $id = 1;

    public function nonStatic(int $value): string
    {
        return (string) (3 * $value);
    }
}
