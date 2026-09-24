<?php

namespace Symfony\Component\Config\Tests\Fixtures;

use Symfony\Component\Config\Resource\ResourceInterface;

class ResourceFailingOnUnserialize implements ResourceInterface
{
    public function __toString(): string
    {
        return __CLASS__;
    }

    public function __unserialize(array $data): void
    {
        throw new \UnexpectedValueException('Cannot unserialize.');
    }
}
