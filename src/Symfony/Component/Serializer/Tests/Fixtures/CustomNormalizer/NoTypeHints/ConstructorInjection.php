<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints;

class ConstructorInjection
{
    private $name; // string
    private $age; // int
    private $height; // float
    private $handsome; // bool
    private $nameOfFriends; // array
    private $picture; // resource
    private $pet; // null
    private $relation; // DummyObject
    private $notSet;

    public function __construct($name, $age, $height, $handsome, $nameOfFriends, $picture, $pet, $relation)
    {
        $this->name = $name;
        $this->age = $age;
        $this->height = $height;
        $this->handsome = $handsome;
        $this->nameOfFriends = $nameOfFriends;
        $this->picture = $picture;
        $this->pet = $pet;
        $this->relation = $relation;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAge()
    {
        return $this->age;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getHandsome()
    {
        return $this->handsome;
    }

    public function getNameOfFriends()
    {
        return $this->nameOfFriends;
    }

    public function getPicture()
    {
        return $this->picture;
    }

    public function getPet()
    {
        return $this->pet;
    }

    public function getRelation()
    {
        return $this->relation;
    }

    public function getNotSet()
    {
        return $this->notSet;
    }
}
