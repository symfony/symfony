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

    /**
     * @required
     */
    protected Bar $plopProtected;

    /**
     * @required
     */
    private Bar $plopPrivate;

    public function __construct(A $a)
    {
    }
}
