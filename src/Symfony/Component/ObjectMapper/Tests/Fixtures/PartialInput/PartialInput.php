<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\PartialInput;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: FinalInput::class)]
class PartialInput
{
    public string $uuid;
    public string $name;
    public ?string $email;
    public ?string $website;
}
