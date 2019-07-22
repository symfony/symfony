<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class BarTagClass
{
    public static function getDefaultFooName()
    {
        return 'bar_tag_class';
    }

    public static function getFooBar()
    {
        return 'bar_tab_class_with_defaultmethod';
    }
}
