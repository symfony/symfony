<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MissmatchType;

class OutInterface
{

    public function __construct(
        public BazInterface $item
    ){
    }

}
