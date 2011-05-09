<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

class MagicGetter
{
    public function __get($key)
    {
        return "Magic Get Value";
    }
}