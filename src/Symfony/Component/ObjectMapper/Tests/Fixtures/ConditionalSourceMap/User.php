<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalSourceMap;

class User
{
    public string $name;
    public Address $address;

    public function __construct()
    {
        $this->address = new Address();
    }
}
