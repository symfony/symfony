<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

class ComplexTypesPublicProperties
{
    public DummyObject $simple;
    /**
     * @var DummyObject[]
     */
    public array $array;

    public DummyObject|SmartObject $union;

    public DummyObject&SmartObject $nested;

    /**
     * @var DummyObject|SmartObject[]
     */
    public array $unionArray;

}
