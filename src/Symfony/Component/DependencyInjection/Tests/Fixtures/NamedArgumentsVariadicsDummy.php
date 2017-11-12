<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class NamedArgumentsVariadicsDummy
{
    public function __construct(...$variadics)
    {

    }
}
