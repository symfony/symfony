<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Mapping\ClassMetadata;

use Symfony\Component\Validator\Constraints\Length;

/**
 * Description of EntityStatic
 *
 * @author po_taka <angel.koilov@gmail.com>
 */
class EntityStaticCar extends EntityStaticVehicle
{
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('wheels', new Length(array ('max' => 99,)));
    }
}
