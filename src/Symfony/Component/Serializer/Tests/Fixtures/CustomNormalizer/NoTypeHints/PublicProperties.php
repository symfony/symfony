<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints;

class PublicProperties
{
    public $name; // string
    public $age; // int
    public $height; // float
    public $handsome; // bool
    public $nameOfFriends; // array
    public $picture; // resource
    public $pet; // null
    public $relation; // DummyObject
    public $notSet;

}
