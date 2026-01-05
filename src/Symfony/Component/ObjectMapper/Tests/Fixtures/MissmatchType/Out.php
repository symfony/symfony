<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MissmatchType;

class Out
{

    public function __construct(
        public Baz $item
    ){

    }

}
