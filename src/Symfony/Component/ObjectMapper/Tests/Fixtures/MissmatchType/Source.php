<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MissmatchType;

class Source
{

    public function __construct(
        public FooBaz $item
    ){
    }

}
