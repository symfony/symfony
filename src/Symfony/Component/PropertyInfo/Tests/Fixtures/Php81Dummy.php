<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php81Dummy
{
    public function getNothing(): never
    {
        throw new \Exception('Oops');
    }

    public function getCollection(): \Traversable&\Countable
    {
    }
}
