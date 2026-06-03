<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class ConstructNotExists
{
    public function __construct(NotExist $notExist)
    {
    }
}
