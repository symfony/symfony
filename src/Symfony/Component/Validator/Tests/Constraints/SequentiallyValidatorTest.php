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

use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\SequentiallyValidator;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class SequentiallyValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
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

        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expects($this->any())->method('getViolations')->willReturn($this->context->getViolations());
        $contextualValidator->expects($this->exactly(2))
            ->method('validate')
            ->withConsecutive(
                [$value, $constraints[0]],
                [$value, $constraints[1]]
            )
            ->willReturn($contextualValidator);

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

        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expects($this->any())->method('getViolations')->willReturn($this->context->getViolations());
        $contextualValidator->expects($this->exactly(2))
            ->method('validate')
            ->withConsecutive(
                [$value, $constraints[0]],
                [$value, $constraints[1]]
            )
            ->will($this->onConsecutiveCalls(
                // Noop, just return the validator:
                $this->returnValue($contextualValidator),
                // Add violation on second call:
                $this->returnCallback(function () use ($contextualValidator) {
                    $this->context->getViolations()->add($violation = new ConstraintViolation('regex error', null, [], null, '', null, null, 'regex'));

                    return $contextualValidator;
                }
            )));

        $this->validator->validate($value, new Sequentially($constraints));

        $this->assertCount(1, $this->context->getViolations());
    }
}
