<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject;

class SetterInjection
{
    private string $name; // string
    private int $age; // int
    private float $height; // float
    private bool $handsome; // bool
    private array $nameOfFriends; // array
    private $picture; // resource
    private null|string $pet; // null
    private DummyObject $relation; // DummyObject
    private string $notSet = 'foobar';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        $this->age = $age;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): void
    {
        $this->height = $height;
    }

    public function isHandsome(): bool
    {
        return $this->handsome;
    }

    public function setHandsome(bool $handsome): void
    {
        $this->handsome = $handsome;
    }

    public function getNameOfFriends(): array
    {
        return $this->nameOfFriends;
    }

    public function setNameOfFriends(array $nameOfFriends): void
    {
        $this->nameOfFriends = $nameOfFriends;
    }

    public function getPicture()
    {
        return $this->picture;
    }

    public function setPicture($picture): void
    {
        $this->picture = $picture;
    }

    public function getPet(): ?string
    {
        return $this->pet;
    }

    public function setPet(?string $pet): void
    {
        $this->pet = $pet;
    }

    public function getRelation(): DummyObject
    {
        return $this->relation;
    }

    public function setRelation(DummyObject $relation): void
    {
        $this->relation = $relation;
    }

    public function getNotSet(): string
    {
        return $this->notSet;
    }
}
