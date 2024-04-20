<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

use Symfony\Component\ErrorHandler\Tests\Fixtures\FinalProperty\FinalProperty;

class OverrideFinalProperty extends FinalProperty
{
    public $pub;
    protected $prot;
    private $priv;
    protected $implicitlyFinal;
    protected string $typedSoNotFinal;
    /**
     * @deprecated
     */
    protected $deprecated;
}
