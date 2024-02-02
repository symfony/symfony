<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject;

class ConstructorInjection
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

    public function __construct(string $name, int $age, float $height, bool $handsome, array $nameOfFriends, $picture, ?string $pet, DummyObject $relation)
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function isHandsome(): bool
    {
        return $this->handsome;
    }

    public function getNameOfFriends(): array
    {
        return $this->nameOfFriends;
    }

    public function getPicture()
    {
        return $this->picture;
    }

    public function getPet(): ?string
    {
        return $this->pet;
    }

    public function getRelation(): DummyObject
    {
        return $this->relation;
    }

    public function getNotSet(): string
    {
        return $this->notSet;
    }


}
