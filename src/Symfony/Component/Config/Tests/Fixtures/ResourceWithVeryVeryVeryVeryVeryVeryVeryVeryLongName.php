<?php

namespace Symfony\Component\Config\Tests\Fixtures;

use Symfony\Component\Config\Resource\ResourceInterface;

class ResourceWithVeryVeryVeryVeryVeryVeryVeryVeryLongName implements ResourceInterface
{
    public function __construct(private string $resource)
    {
    }

    public function __toString(): string
    {
        return __CLASS__;
    }
}
