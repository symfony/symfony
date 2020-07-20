<?php

namespace Symfony\Component\VarDumper\Tests\Fixtures;

class Php74
{
    public $p1 = 123;
    public \stdClass $p2;

    public function __construct()
    {
        $this->p2 = new \stdClass();
    }
}
