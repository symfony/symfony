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

use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Some;
use Symfony\Component\Validator\Constraints\SomeValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
class SomeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return $this->validator = new SomeValidator();
    }

    /**
     * Tear down method.
     */
    protected function tearDown()
    {
        $this->validator = null;
    }

    /**
     * Tests that if null, just valid.
     */
    public function testNullIsValid()
    {
        $this->validator->validate(
            null,
            new Some(
                [
                    'constraints' => [
                        new Range(['min' => 4]),
                    ],
                    'min' => 1,
                ]
            )
        );
        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foo.barbar', new Some(new Range(['min' => 4])));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function testThrowsExceptionMinGreatThanMax()
    {
        $this->validator->validate(
            null,
            new Some(
                [
                    'constraints' => [
                        new Range(['min' => 4]),
                    ],
                    'min' => 3,
                    'max' => 1,
                ]
            )
        );
    }

    /**
     * Testing when min and max are defined.
     */
    public function testMinAndMax()
    {
        $this->validator->validate(
            null,
            new Some(
                [
                    'constraints' => [
                        new Range(['min' => 4]),
                    ],
                    'min' => 1,
                    'max' => 10,
                ]
            )
        );

        $this->assertNoViolation();
    }

    /**
     * Testing when just max is defined.
     */
    public function testMax()
    {
        $this->validator->validate(
            null,
            new Some(
                [
                    'constraints' => [
                        new Range(['min' => 4]),
                    ],
                    'max' => 10,
                ]
            )
        );

        $this->assertNoViolation();
    }

    /**
     * Validates success min.
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessMinValidate($array)
    {
        $constraint1 = new Range(['min' => 2]);
        $constraint2 = new Range(['min' => 7]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                    'min' => 3,
                ]
            ));

        $this->assertNoViolation();
    }

    /**
     * Not validates success min.
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessMinValidate($array)
    {
        $constraint1 = new Range(['min' => 2]);
        $constraint2 = new Range(['min' => 7]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                    'min' => 5,
                ]
            )
        );

        $this->assertCount(0, $this->context->getViolations());
    }

    /**
     * Validates success min.
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessMinMaxValidate($array)
    {
        $constraint1 = new Range(['min' => 2]);
        $constraint2 = new Range(['min' => 7]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                    'min' => 2,
                    'max' => 4,
                ]
            )
        );
        $this->assertCount(1, $this->context->getViolations());
    }

    /**
     * Validates not success min.
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessMinMaxValidate($array)
    {
        $constraint1 = new Range(['min' => 2]);
        $constraint2 = new Range(['min' => 7]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                    'min' => 1,
                    'max' => 3,
                ]
            )
        );

        $this->assertCount(1, $this->context->getViolations());
    }

    /**
     * Validates success max.
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessMaxValidate($array)
    {
        $constraint1 = new Range(['min' => 2]);
        $constraint2 = new Range(['min' => 7]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                    'max' => 5,
                ]
            )
        );

        $this->assertCount(1, $this->context->getViolations());
    }

    /**
     * Validates not success max.
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessMaxValidate($array)
    {
        $constraint1 = new Range(['min' => 2]);
        $constraint2 = new Range(['min' => 7]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                    'max' => 2,
                ]
            )
        );
        $this->assertCount(1, $this->context->getViolations());
    }

    /**
     * Adds validateValue assertions.
     */
    protected function setValidateValueAssertions($array, $constraint1, $constraint2)
    {
        $iteration = 0;
        foreach ($array as $key => $value) {
            $this->expectValidateValueAt($iteration++, '['.$key.']', $value, [$constraint1, $constraint2]);
        }
    }

    /**
     * Data provider.
     */
    public function getValidArguments()
    {
        return [
            [[5, 6, 7]],
            [new \ArrayObject([5, 6, 7])],
        ];
    }
}
