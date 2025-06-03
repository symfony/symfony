<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributedClasses;

trait FooTrait
{
    public function doBar()
    {
        self::class;
        if (true) {
        }
    }
}
