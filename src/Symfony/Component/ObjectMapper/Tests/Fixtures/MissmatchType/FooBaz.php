<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MissmatchType;

class FooBaz
{

    public function __construct(
        public string $foo,
        public string $baz,
    ){

    }

}
