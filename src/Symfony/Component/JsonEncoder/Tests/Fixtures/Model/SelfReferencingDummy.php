<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Attribute\EncodedName;

class SelfReferencingDummy
{
    #[EncodedName('@self')]
    public ?self $self = null;
}
