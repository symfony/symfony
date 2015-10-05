<?php

namespace ClassCons;

class Foo
{
    public function __construct()
    {
        \Foo\TBar/* foo */::class;
    }
}
