<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(ConstructorTarget::class)]
class InputSource
{
    #[Map(if: new NotNullCondition)]
    public ?string $name = null;
}
