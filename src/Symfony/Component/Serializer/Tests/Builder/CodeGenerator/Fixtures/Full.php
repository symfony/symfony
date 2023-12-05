<?php

/*
 * This is a fixture class.
 * We use it for verifying the code generation.
 */

namespace Test\CodeGenerator\Fixtures;

use Test\CodeGenerator\Fixtures\Cat;
use Test\CodeGenerator\Fixtures\Foo;
use Test\CodeGenerator\Fixtures\Bar;
use Test\CodeGenerator\Fixtures\MyAttribute;

/**
 * Perfect class comment.
 * 
 * It has some lines
 */
#[MyAttribute(name: "test")]
class Full extends Cat implements Foo, Bar
{
    private string $name;

    public function __construct(string $name = 'foobar')
    {
        $this->name = $name;
    }

    /**
     * Returns the name of the cat
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

}
