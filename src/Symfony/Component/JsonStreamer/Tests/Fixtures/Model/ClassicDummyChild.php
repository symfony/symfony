<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class ClassicDummyChild extends ClassicDummy implements DummyInterface
{
    public bool $child = true;
}
