<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeTaggedPriority;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.handler', ['priority' => 10])]
class FirstHandler implements TaggedHandlerInterface
{
}
