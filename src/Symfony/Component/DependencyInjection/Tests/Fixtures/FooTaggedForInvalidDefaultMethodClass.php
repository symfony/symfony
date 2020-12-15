<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooTaggedForInvalidDefaultMethodClass
{
    public function getMethodShouldBeStatic()
    {
        return 'anonymous_foo_class_with_default_method';
    }

    protected static function getMethodShouldBePublicInsteadProtected()
    {
        return 'anonymous_foo_class_with_default_method';
    }

    private static function getMethodShouldBePublicInsteadPrivate()
    {
        return 'anonymous_foo_class_with_default_method';
    }
}
