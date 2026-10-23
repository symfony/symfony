<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithNullableRepeatedDummies
{
    public ?ClassicDummy $first = null;
    public ?ClassicDummy $second = null;
}
