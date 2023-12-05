<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;


class ComplexTypesConstructor
{
    private DummyObject $simple;

    /**
     * @var DummyObject[]
     */
    private array $array;

    private DummyObject|SmartObject $union;

    private DummyObject&SmartObject $nested;

    /**
     * @var DummyObject|SmartObject[]
     */
    private array $unionArray;

    /**
     * @var string[]
     */
    private array $simpleArray;

    /**
     * @param DummyObject $simple
     * @param string[] $simpleArray
     * @param DummyObject[] $array
     * @param DummyObject|SmartObject $union
     * @param DummyObject&SmartObject $nested
     * @param (DummyObject|SmartObject)[] $unionArray
     */
    public function __construct(DummyObject $simple, array $simpleArray, array $array, SmartObject|DummyObject $union, DummyObject&SmartObject $nested, array $unionArray)
    {
        $this->simple = $simple;
        $this->array = $array;
        $this->union = $union;
        $this->nested = $nested;
        $this->unionArray = $unionArray;
        $this->simpleArray = $simpleArray;
    }

    public function getSimple(): DummyObject
    {
        return $this->simple;
    }


    public function getArray(): array
    {
        return $this->array;
    }


    public function getUnion(): SmartObject|DummyObject
    {
        return $this->union;
    }


    public function getNested(): DummyObject&SmartObject
    {
        return $this->nested;
    }

    public function getUnionArray(): array
    {
        return $this->unionArray;
    }

    /**
     * @return array
     */
    public function getSimpleArray(): array
    {
        return $this->simpleArray;
    }
}
