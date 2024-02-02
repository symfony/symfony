<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

class InheritanceChild extends InheritanceParent
{
    public string $childName; // string
    protected int $childAge; // int
    private float $childHeight; // float
    private bool $childCute; // bool

    public function __construct(bool $childCute, bool $cute)
    {
        $this->childCute = $childCute;
        parent::__construct($cute);
    }

    public function getChildCute(): bool
    {
        return $this->childCute;
    }

    public function getChildAge(): int
    {
        return $this->childAge;
    }

    public function setChildAge(int $childAge): void
    {
        $this->childAge = $childAge;
    }

    public function getChildHeight(): float
    {
        return $this->childHeight;
    }

    public function setChildHeight(float $childHeight): void
    {
        $this->childHeight = $childHeight;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        $this->age = $age;
    }
}
