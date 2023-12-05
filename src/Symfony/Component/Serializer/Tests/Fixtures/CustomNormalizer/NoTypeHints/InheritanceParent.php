<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints;

class InheritanceParent
{
    public $name; // string
    protected $age; // int
    protected $height; // float
    private $handsome; // bool
    private $cute; // bool

    public function __construct($cute)
    {
        $this->cute = $cute;
    }

    public function getCute()
    {
        return $this->cute;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height): void
    {
        $this->height = $height;
    }

    public function getHandsome()
    {
        return $this->handsome;
    }

    public function setHandsome($handsome): void
    {
        $this->handsome = $handsome;
    }



}
