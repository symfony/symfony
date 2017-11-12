<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses;

trait FooTrait
{
    public function doBar(): void
    {
        $baz = self::class;
        if (true) {
        }
    }
}
