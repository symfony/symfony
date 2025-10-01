<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

abstract class ReturnTypeParent extends ReturnTypeGrandParent implements ReturnTypeParentInterface
{
    const FOO = 'foo';

    /**
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * No return declared here
     */
    public function returnTypeGrandParent()
    {
    }

    /**
     * @return string
     */
    abstract public function realReturnTypeMustBeThere(): string;

    /**
     * @return float
     */
    public function realReturnTypeIsAlreadyThere()
    {
    }

    /**
     * @return iterable|null
     */
    abstract public function realReturnTypeIsAlreadyThereWithNull();

    /**
     * @return resource
     */
    public function oneCommonNonObjectReturnedType()
    {
    }

    /**
     *  @return resource|null
     */
    public function oneCommonNonObjectReturnedTypeWithNull()
    {
    }

    /**
     * @return void
     */
    public function oneNonNullableReturnableType()
    {
    }

    /**
     * @return void|null
     */
    public function oneNonNullableReturnableTypeWithNull()
    {
    }

    /**
     * @return array The array
     */
    public function oneNullableReturnableType()
    {
    }

    /**
     * @return bool|null
     */
    public function oneNullableReturnableTypeWithNull()
    {
    }

    /**
     * @return \ArrayIterator
     */
    public function oneOtherType()
    {
    }

    /**
     * @return \ArrayIterator|null
     */
    public function oneOtherTypeWithNull()
    {
    }

    /**
     * @return int|self
     */
    public function twoNullableReturnableTypes()
    {
    }

    /**
     * @return null|null
     */
    public function twoNullEdgeCase()
    {
    }

    /**
     * @return bool|string|null
     */
    public function threeReturnTypes()
    {
    }

    /**
     * @return self
     */
    public function throughDoc()
    {
    }

    /**
     * @return self
     */
    public function optOutThroughDoc()
    {
    }

    /**
     * @return \ArrayIterator[]|\DirectoryIterator[]
     */
    public function manyIterables()
    {
    }

    /**
     * Something before.
     *
     * @return object
     */
    public function nullableReturnableTypeNormalization()
    {
    }

    /**
     * @annotation before
     * @return VOID
     */
    public function nonNullableReturnableTypeNormalization()
    {
    }

    /**
     * @return \ArrayIterator[]
     */
    public function bracketsNormalization()
    {
    }

    /**
     * @return false
     */
    public function booleanNormalization()
    {
    }

    /**
     * @return callable(\Throwable $reason, mixed $value)
     */
    public function callableNormalization1()
    {
    }

    /**
     * @return callable ($a, $b)
     */
    public function callableNormalization2()
    {
    }

    /**
     * @return \ArrayIterator
     */
    public function otherTypeNormalization()
    {
    }

    /**
     * @return array<string, int>
     */
    public function arrayWithLessThanSignNormalization()
    {
    }

    /**
     * @return $this
     */
    public function this()
    {
    }

    /**
     * @return mixed
     */
    public function mixed()
    {
    }

    /**
     * @return mixed|null
     */
    public function nullableMixed()
    {
    }

    /**
     * @return static
     */
    public function static()
    {
    }

    /**
     * @return false
     */
    public function false()
    {
    }

    /**
     * @return true
     */
    public function true()
    {
    }

    /**
     * @return never
     */
    public function never()
    {
    }

    /**
     * @return null
     */
    public function null()
    {
    }

    /**
     * @return int
     */
    public function notExtended()
    {
    }

    /**
     * @return self::FOO
     */
    public function classConstant()
    {
    }
}
