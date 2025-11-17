<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DefaultLazy;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: OrderTarget::class)]
class OrderSource
{
    public ?int $id = null;
    public ?UserSource $user = null;
}
