<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooTagClass
{
    public static function getDefaultFooName()
    {
        return 'foo_tag_class';
    }

    public static function getPriority(): int
    {
        // Should be more than BarTagClass. More because this class is after
        // BarTagClass (order by name). So we want to ensure it will be before it
        return 20;
    }
}
