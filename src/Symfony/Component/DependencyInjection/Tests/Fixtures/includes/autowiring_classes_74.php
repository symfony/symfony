<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

class PropertiesInjection
{
    /**
     * @required
     */
    public Bar $plop;

    /**
     * @required
     */
    public $plip;

    public function __construct(A $a)
    {
    }
}
