<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeTaggedPriority;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.handler')]
interface TaggedHandlerInterface
{
}
