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

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\SequentiallyValidator;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

class SequentiallyValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SequentiallyValidator
    {
        return new SequentiallyValidator();
    }

    public function testWalkThroughConstraints()
    {
        $constraints = [
            new Type('number'),
            new Range(['min' => 4]),
        ];

        $value = 6;

        $this->expectValidateValue(0, $value, [$constraints[0]]);
        $this->expectValidateValue(1, $value, [$constraints[1]]);

        $this->validator->validate($value, new Sequentially($constraints));

        $this->assertNoViolation();
    }

    public function testStopsAtFirstConstraintWithViolations()
    {
        $constraints = [
            new Type('string'),
            new Regex(['pattern' => '[a-z]']),
            new NotEqualTo('Foo'),
        ];

        $value = 'Foo';

        $this->expectValidateValue(0, $value, [$constraints[0]]);
        $this->expectFailingValueValidation(1, $value, [$constraints[1]], null, new ConstraintViolation('regex error', null, [], null, '', null, null, 'regex'));

        $this->validator->validate($value, new Sequentially($constraints));

        $this->assertCount(1, $this->context->getViolations());
    }

    public function testNestedConstraintsAreNotExecutedWhenGroupDoesNotMatch()
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate(50, new Sequentially([
            'constraints' => [
                new GreaterThan([
                    'groups' => 'senior',
                    'value' => 55,
                ]),
                new Range([
                    'groups' => 'adult',
                    'min' => 18,
                    'max' => 55,
                ]),
            ],
            'groups' => ['adult', 'senior'],
        ]), 'adult');

        $this->assertCount(0, $violations);
    }
}
