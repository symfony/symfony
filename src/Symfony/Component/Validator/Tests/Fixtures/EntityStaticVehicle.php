<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Mapping\ClassMetadata;

use Symfony\Component\Validator\Constraints\Length;

/**
 * Description of EntityStaticVehicle
 *
 * @author po_taka <angel.koilov@gmail.com>
 */
class EntityStaticVehicle
{
    public $wheels;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('wheels', new Length(array ('max' => 99,)));
    }
}
