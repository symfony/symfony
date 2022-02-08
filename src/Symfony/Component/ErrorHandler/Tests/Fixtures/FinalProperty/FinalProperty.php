<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalProperty;

class FinalProperty
{
    /**
     * @final
     */
    public $pub;

    /**
     * @final
     */
    protected $prot;

    /**
     * @final
     */
    private $priv;
}
