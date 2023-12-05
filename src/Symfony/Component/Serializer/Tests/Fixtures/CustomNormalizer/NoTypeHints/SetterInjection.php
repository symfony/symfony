<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints;

class SetterInjection
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

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getAge()
    {
        return $this->age;
    }

    public function setAge($age)
    {
        $this->age = $age;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height)
    {
        $this->height = $height;
    }

    public function isHandsome()
    {
        return $this->handsome;
    }

    public function setHandsome($handsome)
    {
        $this->handsome = $handsome;
    }

    public function getNameOfFriends()
    {
        return $this->nameOfFriends;
    }

    public function setNameOfFriends($nameOfFriends)
    {
        $this->nameOfFriends = $nameOfFriends;
    }

    public function getPicture()
    {
        return $this->picture;
    }

    public function setPicture($picture)
    {
        $this->picture = $picture;
    }

    public function getPet()
    {
        return $this->pet;
    }

    public function setPet($pet)
    {
        $this->pet = $pet;
    }

    public function getRelation()
    {
        return $this->relation;
    }

    public function setRelation($relation)
    {
        $this->relation = $relation;
    }

    public function getNotSet()
    {
        return $this->notSet;
    }




}
