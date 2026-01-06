<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MissmatchType;

class OutUnion
{

    public function __construct(
        public Foo|Baz $item
    ){
    }

}
