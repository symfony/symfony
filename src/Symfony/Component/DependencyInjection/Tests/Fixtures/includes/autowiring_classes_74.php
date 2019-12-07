<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

class PropertiesInjection
{
    /**
     * @required
     */
    public Bar $plop;

    public function __construct(A $a)
    {
    }
}
