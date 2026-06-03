<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadAttributes;

use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'dev')]
#[WhenNot(env: 'test')]
class WhenNotWhenFoo
{
}
