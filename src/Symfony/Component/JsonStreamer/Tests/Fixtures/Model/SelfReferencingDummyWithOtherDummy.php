<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

final class SelfReferencingDummyWithOtherDummy
{
    public ClassicDummy $otherDummy;
    public ?self $self = null;
}
