<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Attribute;

use Symfony\Component\HttpKernel\Attribute\ControllerAttributeInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class FooController implements ControllerAttributeInterface
{
    public function __construct(
        public string $bar
    ) {}
}
