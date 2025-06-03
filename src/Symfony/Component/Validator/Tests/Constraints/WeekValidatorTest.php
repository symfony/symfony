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

use Symfony\Component\Validator\Constraints\Week;
use Symfony\Component\Validator\Constraints\WeekValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

class WeekValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): WeekValidator
    {
        return new WeekValidator();
    }

    /**
     * @dataProvider provideWeekNumber
     */
    public function testWeekIsValidWeekNumber(string|\Stringable $value, bool $expectedViolation)
    {
        $constraint = new Week();
        $this->validator->validate($value, $constraint);

        if ($expectedViolation) {
            $this->buildViolation('This value is not a valid week.')
                ->setCode(Week::INVALID_WEEK_NUMBER_ERROR)
                ->setParameter('{{ value }}', $value)
                ->assertRaised();

            return;
        }

        $this->assertNoViolation();
    }

    public static function provideWeekNumber()
    {
        yield ['2015-W53', false]; // 2015 has 53 weeks
        yield ['2020-W53', false]; // 2020 also has 53 weeks
        yield ['2024-W53', true]; // 2024 has 52 weeks
        yield [new StringableValue('2024-W53'), true];
    }

    public function testBounds()
    {
        $constraint = new Week(min: '2015-W10', max: '2016-W25');

        $this->validator->validate('2015-W10', $constraint);
        $this->assertNoViolation();

        $this->validator->validate('2016-W25', $constraint);
        $this->assertNoViolation();
    }

    public function testTooLow()
    {
        $constraint = new Week(min: '2015-W10');

        $this->validator->validate('2015-W08', $constraint);
        $this->buildViolation('This value should not be before week "{{ min }}".')
            ->setInvalidValue('2015-W08')
            ->setParameter('{{ min }}', '2015-W10')
            ->setCode(Week::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testTooHigh()
    {
        $constraint = new Week(max: '2016-W25');

        $this->validator->validate('2016-W30', $constraint);
        $this->buildViolation('This value should not be after week "{{ max }}".')
            ->setInvalidValue('2016-W30')
            ->setParameter('{{ max }}', '2016-W25')
            ->setCode(Week::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public function testWithNewLine()
    {
        $this->validator->validate("2015-W10\n", new Week());

        $this->buildViolation('This value does not represent a valid week in the ISO 8601 format.')
            ->setCode(Week::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testInvalidValues(string $value)
    {
        $this->validator->validate($value, new Week());

        $this->buildViolation('This value does not represent a valid week in the ISO 8601 format.')
            ->setCode(Week::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testNonStringValues(mixed $value)
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Expected argument of type "string", ".*" given/');

        $this->validator->validate($value, new Week());
    }

    public static function provideInvalidValues()
    {
        yield ['1970-01'];
        yield ['1970-W00'];
        yield ['1970-W54'];
        yield ['1970-W100'];
        yield ['1970-W01-01'];
        yield ['-W01'];
        yield ['24-W01'];
    }

    public static function provideInvalidTypes()
    {
        yield [true];
        yield [false];
        yield [1];
        yield [1.1];
        yield [[]];
        yield [new \stdClass()];
    }
}
