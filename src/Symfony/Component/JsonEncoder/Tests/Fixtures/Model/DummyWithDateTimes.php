<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

class DummyWithDateTimes
{
    public \DateTimeInterface $interface;
    public \DateTimeImmutable $immutable;
    public \DateTime $mutable;
}
