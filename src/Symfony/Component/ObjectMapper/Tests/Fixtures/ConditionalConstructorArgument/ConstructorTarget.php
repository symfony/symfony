<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument;

class ConstructorTarget
{
    public function __construct(public string $name)
    {
    }
}
