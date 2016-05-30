<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses;

trait FooTrait
{
    public function doBar()
    {
        $baz = self::class;
        if (true) {
        }
    }
}
