<?php

namespace Symfony\Component\VarDumper\Tests\Fixtures;

class Php74
{
    public $p1 = 123;
    public \stdClass $p2;
    public \stdClass $p3;

    public function __construct()
    {
        $this->p2 = new \stdClass();
        $this->p3 = &$this->p2;
    }
}
