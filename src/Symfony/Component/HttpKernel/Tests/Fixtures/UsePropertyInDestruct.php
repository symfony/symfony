<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class UsePropertyInDestruct
{
    public string $name;
    public $parent = null;

    public function __destruct()
    {
        if ($this->parent !== null) {
            $this->parent->name = '';
        }
    }
}
