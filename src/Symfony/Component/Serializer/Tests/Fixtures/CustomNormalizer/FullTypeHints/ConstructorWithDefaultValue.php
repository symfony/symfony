<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

class ConstructorWithDefaultValue
{
    private int $foo;
    private DummyObject|SmartObject|null $union;

    public function __construct(int $foo = 4711,  SmartObject|DummyObject|null $union = null, $x = new SmartObject())
    {
        $this->foo = $foo;
        $this->union = $union;
    }

    public function getFoo(): int
    {
        return $this->foo;
    }

    public function getUnion(): SmartObject|DummyObject|null
    {
        return $this->union;
    }
}
