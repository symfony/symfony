<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeTagPriority;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'app.handler', attributes: ['priority' => 10])]
final class ThirdHandler implements HandlerInterface
{
    public function name(): string
    {
        return 'third';
    }
}
