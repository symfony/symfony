<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Stringable;

enum FooBackedEnum: string
{
    case BAR = 'bar';
    case FOO = 'foo';
}
