<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeTagPriority;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'app.handler', attributes: ['priority' => 100])]
final class FirstHandler implements HandlerInterface
{
    public function name(): string
    {
        return 'first';
    }
}
