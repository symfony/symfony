<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

trait AttributeWithMissingUseTrait
{
    #[MissingInheritedConstantAttribute]
    public const INHERITED_CONSTANT = 1;

    #[MissingTraitPropertyAttribute]
    public $traitProperty;

    #[MissingInheritedPropertyAttribute]
    public $inheritedProperty;

    #[MissingTraitMethodAttribute]
    public function traitMethod()
    {
    }
}
