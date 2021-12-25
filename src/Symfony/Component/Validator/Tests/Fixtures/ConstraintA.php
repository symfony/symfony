<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraint;

/** @Annotation */
#[\Attribute]
class ConstraintA extends Constraint
{
    public $property1;
    public $property2;

    public function getDefaultOption(): ?string
    {
        return 'property2';
    }

    public function getTargets(): string|array
    {
        return [self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT];
    }
}
