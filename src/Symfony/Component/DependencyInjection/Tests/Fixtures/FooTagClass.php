<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('foo_tag_class')]
class FooTagClass
{
    /**
     * @deprecated since Symfony 8.1
     */
    public static function getDefaultFooName()
    {
        return 'foo_tag_class';
    }

    /**
     * @deprecated since Symfony 8.1
     */
    public static function getPriority(): int
    {
        // Should be more than BarTagClass. More because this class is after
        // BarTagClass (order by name). So we want to ensure it will be before it
        return 20;
    }
}
