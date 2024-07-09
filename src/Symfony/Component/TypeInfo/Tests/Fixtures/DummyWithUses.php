<?php

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

use Symfony\Component\TypeInfo\Type;
use \DateTimeInterface;
use \DateTimeImmutable as DateTime;

final class DummyWithUses
{
    private DateTimeInterface $createdAt;

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getType(): Type
    {
        throw new \LogicException('Should not be called.');
    }
}
