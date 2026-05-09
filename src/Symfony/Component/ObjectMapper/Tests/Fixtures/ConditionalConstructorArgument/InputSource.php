<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\IsNotNull;

#[Map(ConstructorTarget::class)]
class InputSource
{
    #[Map(if: new IsNotNull())]
    public ?string $name = null;
}
