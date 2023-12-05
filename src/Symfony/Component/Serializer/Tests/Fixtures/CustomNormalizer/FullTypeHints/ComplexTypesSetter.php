<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;


use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject;
use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SmartObject;

class ComplexTypesSetter
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

    public function setSimple(DummyObject $simple): void
    {
        $this->simple = $simple;
    }

    public function setArray(array $array): void
    {
        $this->array = $array;
    }

    public function setUnion(SmartObject|DummyObject $union): void
    {
        $this->union = $union;
    }

    public function setNested(DummyObject&SmartObject $nested): void
    {
        $this->nested = $nested;
    }

    public function setUnionArray(array $unionArray): void
    {
        $this->unionArray = $unionArray;
    }
}
