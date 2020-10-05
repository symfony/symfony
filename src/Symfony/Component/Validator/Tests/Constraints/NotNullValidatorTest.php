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

use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\NotNullValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class NotNullValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new NotNullValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->validator->validate($value, new NotNull());

        $this->assertNoViolation();
    }

    public function getValidValues()
    {
        return [
            [0],
            [false],
            [true],
            [''],
        ];
    }

    /**
     * @dataProvider provideInvalidConstraints
     */
    public function testNullIsInvalid(NotNull $constraint)
    {
        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(NotNull::IS_NULL_ERROR)
            ->assertRaised();
    }

    public function provideInvalidConstraints(): iterable
    {
        yield 'Doctrine style' => [new NotNull([
            'message' => 'myMessage',
        ])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'named parameters' => [eval('return new \Symfony\Component\Validator\Constraints\NotNull(message: "myMessage");')];
        }
    }
}
