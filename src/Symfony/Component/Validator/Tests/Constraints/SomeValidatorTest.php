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
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Some;
use Symfony\Component\Validator\Constraints\SomeValidator;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class SomeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SomeValidator
    {
        return new SomeValidator();
    }

    /**
     * @dataProvider getValidValueCases
     */
    public function testValidValue($value, $constraints)
    {
        $this->assertCount(0, Validation::createValidator()->validate($value, new Some(['constraints' => $constraints])));
    }

    /**
     * @dataProvider getInvalidValueCases
     */
    public function testInvalidValue($value, $constraints)
    {
        $this->assertCount(1, Validation::createValidator()->validate($value, new Some(['constraints' => $constraints])));
    }

    /**
     * @dataProvider getInvalidValueExactLimitCases
     */
    public function testExactLimit(int $exactly, int $expectedCount, array $value, array $constraints, string $expectedMessage)
    {
        $violations = Validation::createValidator()->validate($value, new Some(['exactly' => $exactly, 'constraints' => $constraints, 'includeInternalMessages' => false]));

        $this->assertCount(1, $violations);

        $this->assertSame($value, $violations[0]->getInvalidValue());
        $this->assertSame($expectedMessage, $violations[0]->getMessage());
        $this->assertSame('6466f661-8b8e-495d-ac96-408aa2e7ee33', $violations[0]->getCode());
        $this->assertSame(['{{ count }}' => "$expectedCount", '{{ limit }}' => "$exactly"], $violations[0]->getParameters());
        $this->assertInstanceOf(Some::class, $violations[0]->getConstraint());
        $this->assertSame($exactly, $violations[0]->getConstraint()->min);
        $this->assertSame($exactly, $violations[0]->getConstraint()->max);
    }

    /**
     * @dataProvider provideMinLimitCases
     */
    public function testMinLimit(int $min, int $expectedCount, array $value, array $constraints, string $expectedMessage)
    {
        $violations = Validation::createValidator()->validate($value, new Some(['min' => $min, 'constraints' => $constraints, 'includeInternalMessages' => false]));

        $this->assertCount(1, $violations);

        $this->assertSame($value, $violations[0]->getInvalidValue());
        $this->assertSame($expectedMessage, $violations[0]->getMessage());
        $this->assertSame('a7ea059b-f8e6-4e85-a48a-bc5eddc0103b', $violations[0]->getCode());
        $this->assertSame(['{{ count }}' => "$expectedCount", '{{ limit }}' => "$min"], $violations[0]->getParameters());
        $this->assertInstanceOf(Some::class, $violations[0]->getConstraint());
        $this->assertSame($min, $violations[0]->getConstraint()->min);
        $this->assertNull($violations[0]->getConstraint()->max);
    }

    /**
     * @dataProvider provideMaxLimitCases
     */
    public function testMaxLimit(int $max, int $expectedCount, array $value, array $constraints, string $expectedMessage)
    {
        $violations = Validation::createValidator()->validate($value, new Some(['max' => $max, 'constraints' => $constraints, 'includeInternalMessages' => false]));

        $this->assertCount(1, $violations);

        $this->assertSame($value, $violations[0]->getInvalidValue());
        $this->assertSame($expectedMessage, $violations[0]->getMessage());
        $this->assertSame('63d385ab-9101-4195-bc32-7283e13a5283', $violations[0]->getCode());
        $this->assertSame(['{{ count }}' => "$expectedCount", '{{ limit }}' => "$max"], $violations[0]->getParameters());
        $this->assertInstanceOf(Some::class, $violations[0]->getConstraint());
        $this->assertSame($max, $violations[0]->getConstraint()->max);
        $this->assertSame(1, $violations[0]->getConstraint()->min);
    }

    public function testWithIncludedInternalMessages()
    {
        $violations = Validation::createValidator()->validate([true, 1, false], new Some([
            'constraints' => [
                new Type('string'),
                new Length(['min' => 10]),
            ],
        ]));

        $this->assertCount(1, $violations);
        $this->assertSame('At least 1 value should satisfy one of the following constraints: [1] This value should be of type string. [2] This value is too short. It should have 10 characters or more.', $violations[0]->getMessage());
        $this->assertSame('At least {{ limit }} value should satisfy one of the following constraints: [1] This value should be of type string. [2] This value is too short. It should have 10 characters or more.|At least {{ limit }} values should satisfy one of the following constraints: [1] This value should be of type string. [2] This value is too short. It should have 10 characters or more.', $violations[0]->getMessageTemplate());
    }

    public function testGroupsArePropagatedToNestedConstraints()
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate(['a'], new Some([
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
            [null, true, null],
            [new NotNull()],
        ];

        yield [
            [null, 'null', null],
            [new Type('string')],
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
            ['', 'test', 'symfony', null],
            [
                new Length(['min' => 10]),
                new EqualTo(['value' => 'symfony']),
            ],
        ];

        yield [
            [1, 5, 20],
            [new EqualTo(20)],
        ];

        yield [
            ['a', 'B', 'c'],
            [new Regex('/B/')],
        ];
    }

    public function getInvalidValueCases(): iterable
    {
        yield [
            [null, null, null],
            [new NotNull()],
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
            ['', 'test', 'symfony'],
            [
                new Length(['min' => 10]),
                new EqualTo(['value' => 'Symfony']),
            ],
        ];

        yield [
            ['a', 'b', 'c'],
            [new Regex('/B/')],
        ];
    }

    public function getInvalidValueExactLimitCases(): iterable
    {
        // too low
        yield [
            1, 0,
            ['a', 'b', 'c'],
            [new EqualTo('d')],
            'Exactly 1 value should satisfy one of the following constraints:',
        ];

        yield [
            2, 1,
            ['a', 'b', 'c', 'd'],
            [new EqualTo('d')],
            'Exactly 2 values should satisfy one of the following constraints:',
        ];

        yield [
            3, 2,
            ['a', 'b', 'c', 'd', 'd'],
            [new EqualTo('d')],
            'Exactly 3 values should satisfy one of the following constraints:',
        ];

        // too many
        yield [
            0, 1,
            ['a', 'b', 'c', 'd'],
            [new EqualTo('d')],
            'Exactly 0 values should satisfy one of the following constraints:',
        ];

        yield [
            1, 2,
            ['a', 'b', 'c', 'd', 'd'],
            [new EqualTo('d')],
            'Exactly 1 value should satisfy one of the following constraints:',
        ];

        yield [
            2, 3,
            ['a', 'b', 'c', 'd', 'd', 'd'],
            [new EqualTo('d')],
            'Exactly 2 values should satisfy one of the following constraints:',
        ];

        yield [
            3, 4,
            ['a', 'b', 'c', 'd', 'd', 'd', 'd'],
            [new EqualTo('d')],
            'Exactly 3 values should satisfy one of the following constraints:',
        ];
    }

    public function provideMinLimitCases(): iterable
    {
        yield [
            1, 0,
            ['a', 'b', 'c'],
            [new EqualTo('d')],
            'At least 1 value should satisfy one of the following constraints:',
        ];

        yield [
            2, 1,
            ['a', 'b', 'c', 'd'],
            [new EqualTo('d')],
            'At least 2 values should satisfy one of the following constraints:',
        ];

        yield [
            3, 2,
            ['a', 'b', 'c', 'd', 'd'],
            [new EqualTo('d')],
            'At least 3 values should satisfy one of the following constraints:',
        ];
    }

    public function provideMaxLimitCases(): iterable
    {
        yield [
            2, 3,
            ['a', 'b', 'c', 'd', 'd', 'd'],
            [new EqualTo('d')],
            'At most 2 values should satisfy one of the following constraints:',
        ];

        yield [
            3, 4,
            ['a', 'b', 'c', 'd', 'd', 'd', 'd'],
            [new EqualTo('d')],
            'At most 3 values should satisfy one of the following constraints:',
        ];

        yield [
            4, 5,
            ['a', 'b', 'c', 'd', 'd', 'd', 'd', 'd'],
            [new EqualTo('d')],
            'At most 4 values should satisfy one of the following constraints:',
        ];
    }
}
