<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

class DummyWithUids
{
    public Uuid $uuid;
    public UuidV7 $uuidV7;
    public Ulid $ulid;
}
