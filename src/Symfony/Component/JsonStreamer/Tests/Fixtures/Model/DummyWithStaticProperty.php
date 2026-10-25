<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithStaticProperty
{
    public static int $instances = 0;
    public int $id = 1;
}
