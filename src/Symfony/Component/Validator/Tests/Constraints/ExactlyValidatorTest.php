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

use Symfony\Component\Validator\Constraints\Exactly;
use Symfony\Component\Validator\Constraints\ExactlyValidator;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
class ExactlyValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return $this->validator = new ExactlyValidator();
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
            new Exactly(
                [
                    'constraints' => [
                        new Range(['min' => 4]),
                    ],
                    'exactly' => 1,
                ]
            )
        );
        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foo.barbar', new Exactly(new Range(['min' => 4])));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function testThrowsExceptionExactlyNotFound()
    {
        $this->validator->validate(
            null,
            new Exactly(
                []
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
            new Exactly(
                [
                    'constraints' => [
                        new Range(['min' => 4]),
                    ],
                    'exactly' => 1,
                ]
            )
        );

        $this->assertNoViolation();
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
