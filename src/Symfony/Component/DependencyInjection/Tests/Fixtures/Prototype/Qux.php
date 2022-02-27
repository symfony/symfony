<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: ['prod', 'dev'])]
class Qux
{
}
