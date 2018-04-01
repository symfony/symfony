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

use Symphony\Component\Validator\Constraint;

/** @Annotation */
class ConstraintWithValue extends Constraint
{
    public $property;
    public $value;

    public function getDefaultOption()
    {
        return 'property';
    }

    public function getTargets()
    {
        return array(self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT);
    }
}
