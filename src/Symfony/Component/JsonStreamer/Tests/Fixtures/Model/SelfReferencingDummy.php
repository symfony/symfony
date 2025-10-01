<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;

class SelfReferencingDummy
{
    #[StreamedName('@self')]
    public ?self $self = null;
}
