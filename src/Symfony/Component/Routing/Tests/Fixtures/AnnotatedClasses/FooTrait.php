<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses;

trait FooTrait
{
    public function doBar()
    {
        self::class;
        if (true) {
        }
    }
}
