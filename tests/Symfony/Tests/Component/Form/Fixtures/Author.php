<?php

namespace Symfony\Tests\Component\Form\Fixtures;

class Author
{
    public $firstName;
    private $lastName;
    private $australian;
    public $child;

    private $privateProperty;

    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    private function getPrivateGetter()
    {
        return 'foobar';
    }

    public function setAustralian($australian)
    {
        $this->australian = $australian;
    }

    public function isAustralian()
    {
        return $this->australian;
    }

    private function isPrivateIsser()
    {
        return true;
    }

    public function getPrivateSetter()
    {
    }

    private function setPrivateSetter($data)
    {
    }
}
