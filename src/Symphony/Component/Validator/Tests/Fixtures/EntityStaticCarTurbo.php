<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Fixtures;

use Symphony\Component\Validator\Mapping\ClassMetadata;
use Symphony\Component\Validator\Constraints\Length;

class EntityStaticCarTurbo extends EntityStaticCar
{
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('wheels', new Length(array('max' => 99)));
    }
}
