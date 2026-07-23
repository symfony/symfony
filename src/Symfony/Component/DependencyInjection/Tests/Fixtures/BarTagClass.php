<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('bar_tag_class')]
class BarTagClass
{
    /**
     * @deprecated since Symfony 8.1
     */
    public static function getDefaultFooName()
    {
        return 'bar_tag_class';
    }

    /**
     * @deprecated since Symfony 8.1
     */
    public static function getFooBar()
    {
        return 'bar_tab_class_with_defaultmethod';
    }

    /**
     * @deprecated since Symfony 8.1
     */
    public static function getPriority(): int
    {
        return 0;
    }
}
