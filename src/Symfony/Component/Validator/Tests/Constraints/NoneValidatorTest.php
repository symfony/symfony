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

use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\None;
use Symfony\Component\Validator\Constraints\NoneValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class NoneValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NoneValidator
    {
        return new NoneValidator();
    }

    /**
     * @dataProvider getValidValueCases
     */
    public function testValidValue($value, $constraints)
    {
        $this->assertCount(0, Validation::createValidator()->validate($value, new None(['constraints' => $constraints])));
    }

    /**
     * @dataProvider getInvalidValueCases
     */
    public function testInvalidValue($value, $constraints)
    {
        $this->assertCount(1, Validation::createValidator()->validate($value, new None(['constraints' => $constraints])));
    }

    public function testGroupsArePropagatedToNestedConstraints()
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate(['d'], new None([
            'constraints' => [
                new EqualTo([
                    'groups' => 'non_default_group',
                    'value' => 'd',
                ]),
            ],
            'groups' => 'non_default_group',
        ]), 'non_default_group');

        $this->assertCount(1, $violations);
    }

    public function getValidValueCases(): iterable
    {
        yield [
            [null, null, null],
            [new NotNull()],
        ];

        yield [
            [false, true, false],
            [new Type('string')],
        ];

        yield [
            [false, false, false],
            [new IsTrue()],
        ];

        yield [
            [true, true, true],
            [new IsFalse()],
        ];

        yield [
            ['', 'test', 'Symfony'],
            [
                new Length(['min' => 10]),
                new EqualTo(['value' => 'symfony']),
            ],
        ];

        yield [
            [1, 5, 19],
            [new EqualTo(20)],
        ];

        yield [
            ['a', 'b', 'c'],
            [new Regex('/B/')],
        ];
    }

    public function getInvalidValueCases(): iterable
    {
        yield [
            [null, true, null],
            [new NotNull()],
        ];

        yield [
            [false, true, false],
            [new IsTrue()],
        ];

        yield [
            [true, false, true],
            [new IsFalse()],
        ];

        yield [
            ['', 'test', 'Symfony'],
            [
                new Length(['min' => 10]),
                new EqualTo(['value' => 'Symfony']),
            ],
        ];

        yield [
            ['a', 'B', 'c'],
            [new Regex('/B/')],
        ];
    }
}
