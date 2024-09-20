<?php

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

final class Dummy extends AbstractDummy
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
