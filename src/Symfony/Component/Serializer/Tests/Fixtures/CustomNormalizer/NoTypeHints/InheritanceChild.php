<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints;

class InheritanceChild extends InheritanceParent
{
    public $childName; // string
    protected $childAge; // int
    private $childHeight; // float
    private $childCute; // bool

    public function __construct($childCute, $cute)
    {
        $this->childCute = $childCute;
        parent::__construct($cute);
    }

    public function getChildCute()
    {
        return $this->childCute;
    }

    public function getChildAge()
    {
        return $this->childAge;
    }

    public function setChildAge($childAge): void
    {
        $this->childAge = $childAge;
    }

    public function getChildHeight()
    {
        return $this->childHeight;
    }

    public function setChildHeight($childHeight): void
    {
        $this->childHeight = $childHeight;
    }

    public function getAge()
    {
        return $this->age;
    }

    public function setAge($age): void
    {
        $this->age = $age;
    }
}
