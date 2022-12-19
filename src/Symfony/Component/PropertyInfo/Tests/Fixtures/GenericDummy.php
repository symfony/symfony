<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class GenericDummy
{
    /**
     * @var TypeVariableDummy<string, mixed>
     */
    public $stringProperty;

    /**
     * @var TypeVariableDummy<\stdClass, mixed>
     */
    public $objectProperty;

    /**
     * @var ?TypeVariableDummy<\stdClass, mixed>
     */
    public $nullableObjectProperty;

    /**
     * @var TypeVariableDummy<mixed, string>
     */
    public $getterPropertyWithClassLevelTemplateReturnString;
}
