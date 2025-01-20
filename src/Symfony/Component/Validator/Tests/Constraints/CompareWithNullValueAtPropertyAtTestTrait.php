<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Tests\Constraints\Fixtures\TypedDummy;

trait CompareWithNullValueAtPropertyAtTestTrait
{
    public function testCompareWithNullValueAtPropertyAt()
    {
        $constraint = $this->createConstraint(['propertyPath' => 'value']);
        $constraint->message = 'Constraint Message';

        $object = new ComparisonTest_Class(null);
        $this->setObject($object);

        $this->validator->validate(5, $constraint);

        $this->assertNoViolation();
    }

    public function testCompareWithUninitializedPropertyAtPropertyPath()
    {
        $this->setObject(new TypedDummy());

        $this->validator->validate(5, $this->createConstraint([
            'message' => 'Constraint Message',
            'propertyPath' => 'value',
        ]));

        $this->assertNoViolation();
    }
}
