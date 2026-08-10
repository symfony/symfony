<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeTagPriority;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'app.handler')]
interface HandlerInterface
{
    public function name(): string;
}
