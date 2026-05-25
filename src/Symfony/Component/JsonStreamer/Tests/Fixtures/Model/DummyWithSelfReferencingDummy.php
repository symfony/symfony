<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

final class DummyWithSelfReferencingDummy
{
    public ClassicDummy $otherDummy;
    public ?SelfReferencingDummyWithOtherDummy $selfReferencing = null;
}
