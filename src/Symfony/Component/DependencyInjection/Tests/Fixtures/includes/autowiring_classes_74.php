<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

// @deprecated since Symfony 6.3, to be removed in 7.0
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
