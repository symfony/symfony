<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithDateTimes
{
    public \DateTimeInterface $interface;
    public \DateTimeImmutable $immutable;
    public \DateTimeImmutable|int $union;
}
