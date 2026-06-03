<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('foo', ['priority' => 2])]
#[AutoconfigureTag('bar')]
class AutoconfigureRepeatedTag
{
}
