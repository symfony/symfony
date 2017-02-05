<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Mapping\ClassMetadata;

use Symfony\Component\Validator\Constraints\Length;

/**
 * Description of EntityStaticParent
 *
 * @author po_taka <angel.koilov@gmail.com>
 */
class EntityStaticCarTurbo extends EntityStaticCar
{
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('wheels', new Length(array ('max' => 99,)));
    }
}
