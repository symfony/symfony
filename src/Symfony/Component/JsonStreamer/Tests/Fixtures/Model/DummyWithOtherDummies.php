<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

final class DummyWithOtherDummies
{
    public string $name;
    public DummyWithNameAttributes $otherDummyOne;
    public ClassicDummy $otherDummyTwo;
}
