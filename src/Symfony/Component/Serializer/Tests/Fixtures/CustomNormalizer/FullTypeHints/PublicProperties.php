<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

class PublicProperties
{
    public string $name; // string
    public int $age; // int
    public float $height; // float
    public bool $handsome; // bool
    public array $nameOfFriends; // array
    public $picture; // resource
    public null|string  $pet; // null
    public DummyObject $relation; // DummyObject
}
