<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration;

class Bindings
{
    private $paramOne;
    private $paramTwo;

    public function __construct($paramOne, $paramTwo)
    {
        $this->paramOne = $paramOne;
        $this->paramTwo = $paramTwo;
    }
}
