<?php

namespace Test\Symfony\Component\ErrorHandler\Tests;

use Test\Symfony\Component\ErrorHandler\Tests\FinalProperty\OutsideFinalProperty;

class OverrideOutsideFinalProperty extends OutsideFinalProperty
{
    public $final;
    protected $notImplicitlyFinalBecauseNotInSymfony;
    /**
     * @deprecated
     */
    protected $deprecated;
}
