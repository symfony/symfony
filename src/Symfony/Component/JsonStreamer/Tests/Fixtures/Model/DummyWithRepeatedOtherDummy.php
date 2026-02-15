<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

final class DummyWithRepeatedOtherDummy
{
    public ?ClassicDummy $one = null;
    public ?ClassicDummy $two = null;
    public ?ClassicDummy $three = null;
    public ?ClassicDummy $four = null;
    public ?ClassicDummy $five = null;
}
