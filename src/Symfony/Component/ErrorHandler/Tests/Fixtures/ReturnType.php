<?php

namespace Test\Symfony\Component\ErrorHandler\Tests;

use Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent;
use Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeInterface;

class ReturnType extends ReturnTypeParent implements ReturnTypeInterface, Fixtures\OutsideInterface
{
    public function __construct() { }
    public function returnTypeGrandParent() { }
    public function returnTypeParentInterface() { }
    public function returnTypeInterface() { }
    public function realReturnTypeMustBeThere(): string { }
    public function realReturnTypeIsAlreadyThere(): float { }
    public function realReturnTypeIsAlreadyThereWithNull(): ?iterable { }
    public function oneCommonNonObjectReturnedType() { }
    public function oneCommonNonObjectReturnedTypeWithNull() { }
    public function oneNonNullableReturnableType() { }
    public function oneNonNullableReturnableTypeWithNull() { }
    public function oneNullableReturnableType() { }
    public function oneNullableReturnableTypeWithNull() { }
    public function oneOtherType() { }
    public function oneOtherTypeWithNull() { }
    public function twoNullableReturnableTypes() { }
    public function twoNullEdgeCase() { }
    public function threeReturnTypes() { }
    /**
     * @return anything - should not trigger
     */
    public function throughDoc() { }
    /**
     * @return parent - same as parent
     */
    public function optOutThroughDoc() { }
    public function manyIterables() { }
    public function nullableReturnableTypeNormalization() { }
    public function nonNullableReturnableTypeNormalization() { }
    public function bracketsNormalization() { }
    public function booleanNormalization() { }
    public function callableNormalization1() { }
    public function callableNormalization2() { }
    public function otherTypeNormalization() { }
    public function arrayWithLessThanSignNormalization() { }
    public function this() { }
    public function mixed() { }
    public function nullableMixed() { }
    public function static() { }
    public function false() { }
    public function true() { }
    public function never() { }
    public function null() { }
    public function outsideMethod() { }
    public function classConstant() { }
}
