<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Attribute;

use Symfony\Component\HttpKernel\Attribute\ControllerAttributeInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class RepeatableFooController implements ControllerAttributeInterface
{
    public function __construct(
        public string $bar
    ) {}
}
