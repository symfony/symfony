<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeTagPriority;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'app.handler', attributes: ['priority' => 50])]
final class SecondHandler implements HandlerInterface
{
    public function name(): string
    {
        return 'second';
    }
}
