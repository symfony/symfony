<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class IntTagClass
{
    public static function getFooBar()
    {
        return 10;
    }

    public static function getPriority(): int
    {
        // Should be more than FooTagClass. More because this class is after
        // FooTagClass (order by name). So we want to ensure it will be before it
        return 30;
    }
}
