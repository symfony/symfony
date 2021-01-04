<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

class_alias(
    ClassAliasTargetClass::class,
    __NAMESPACE__.'\ClassAliasExampleClass'
);

if (false) {
    class ClassAliasExampleClass
    {
    }
}
