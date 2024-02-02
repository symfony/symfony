<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

class NonReadableProperty
{
    private string $name;
    private int $count = 4;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->count = strlen($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFunnyName()
    {
        return $this->name.'_'.$this->count;
    }
}
