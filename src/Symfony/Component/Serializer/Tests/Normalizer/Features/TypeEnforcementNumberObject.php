<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

class TypeEnforcementNumberObject
{
    /**
     * @var float
     */
    public $number;

    public function setNumber($number)
    {
        $this->number = $number;
    }
}
