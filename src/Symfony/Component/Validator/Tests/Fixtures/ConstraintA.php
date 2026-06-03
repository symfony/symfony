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

#[\Attribute]
class ConstraintA extends Constraint
{
    public $property1;
    public $property2;

    public function __construct($property1 = null, $property2 = null, $groups = null)
    {
        parent::__construct(null, $groups);
        $this->property1 = $property1;
        $this->property2 = $property2;
    }

    public function getTargets(): string|array
    {
        return [self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT];
    }
}
