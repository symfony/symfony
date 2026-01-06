<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MissmatchType;

class Baz implements BazInterface
{

    public function __construct(
        public string $baz
    ){
    }

}
