<?php

namespace Symfony\Component\Workflow\Tests;

final class Subject
{
    private $marking;

    public function __construct($marking = null)
    {
        $this->marking = $marking;
    }

    public function getMarking()
    {
        return $this->marking;
    }

    public function setMarking($marking)
    {
        $this->marking = $marking;
    }
}
