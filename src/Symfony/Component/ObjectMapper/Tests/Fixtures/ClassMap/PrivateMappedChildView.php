<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Cost::class)]
final class PrivateMappedChildView extends PrivateMappedParentView
{
}
