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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sum;
use Symfony\Component\Validator\Constraints\SumValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class SumValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SumValidator
    {
        return new SumValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Sum(exactly: 6));
        $this->assertNoViolation();
    }

    public function testInvalidConstraint()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate([], new NotBlank());
    }

    public function testNonIterable()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate('non-iterable', new Sum(exactly: 6));
    }

    public static function getNonNumericValues()
    {
        return [
            [[1, 'a', 3], 'a'],
            [['a' => 1, 'b' => 'x', 'c' => 3], 'x'],
            [new \ArrayIterator([1, 'a', 3]), 'a'],
            [static fn () => (static function () {
                yield 1;
                yield 'a';
                yield 3;
            })(), 'a'],
        ];
    }

    #[DataProvider('getNonNumericValues')]
    public function testNonNumericValue(iterable|callable $values, string $invalid)
    {
        $values = \is_callable($values) ? $values() : $values;

        $constraint = new Sum(min: 0);

        $this->validator->validate($values, $constraint);

        $this->buildViolation($constraint->notNumericMessage)
            ->setParameter('{{ value }}', '"'.$invalid.'"')
            ->setInvalidValue($values)
            ->setCode(Sum::NOT_NUMERIC_ERROR)
            ->assertRaised();
    }

    public function testMinEqualsMaxActsAsExact()
    {
        $values = [1, 2, 3];

        $constraint = new Sum(min: 6, max: 6);
        $this->validator->validate($values, $constraint);

        $this->assertNoViolation();
    }

    public function testMinEqualsMaxViolation()
    {
        $values = [1, 2, 3];

        $constraint = new Sum(min: 7, max: 7);
        $this->validator->validate($values, $constraint);

        $this->buildViolation($constraint->exactMessage)
            ->setParameter('{{ sum }}', 6)
            ->setParameter('{{ limit }}', 7)
            ->setInvalidValue($values)
            ->setCode(Sum::NOT_EQUAL_SUM_ERROR)
            ->assertRaised();
    }

    private function getSum(iterable $values): float
    {
        $sum = 0.0;

        foreach ($values as $value) {
            $sum += (float) $value;
        }

        return $sum;
    }

    private static function get123Generator(): \Generator
    {
        yield 1;
        yield 2;
        yield 3;
    }

    public static function getValidSumValues()
    {
        return [
            [[], 0.0],
            [[1, 2, 3], 6],
            [[1, 2, 3, -4], 2],
            [[-1, -2, -3], -6],
            [[1.5, 2.5, 3], 7],
            [[1.5, 2.5, 3.5], 7.5],
            [[1.5, 2.5, -3.5], 0.5],
            [[-1.5, -2.5, -3.5], -7.5],
            [[0.1, 0.2, 0.3], 0.6],
            [[0.0000001, 0.0000002], 0.0000003],
            [[0.0000001, 0.0000002, -0.0000001], 0.0000002],
            [['1', '2', '3'], 6],
            [['1.5', '2.5'], 4.0],
            [['-1', '-2', '-3'], -6],
            [['a' => 1, 'b' => 2, 'c' => 3], 6],
            [['a' => 1.5, 'b' => 2.5], 4.0],
            [[1, '2', 3.0], 6],
            [new \ArrayIterator([1, 2, 3]), 6],
            [static fn () => self::get123Generator(), 6],
        ];
    }

    #[DataProvider('getValidSumValues')]
    public function testValidValuesForExactSum(iterable|callable $values, int|float $sum)
    {
        $constraint = new Sum(exactly: $sum);
        $this->validator->validate(\is_callable($values) ? $values() : $values, $constraint);

        $this->assertNoViolation();
    }

    public static function getInvalidSumValues()
    {
        return [
            [[], 1.0],
            [[1, 2, 3], 5],
            [[1, 2, 3, -4], 3],
            [[-1, -2, -3], -5],
            [[1.5, 2.5, 3], 6],
            [[1.5, 2.5, 3.5], 8],
            [[1.5, 2.5, -3.5], 1],
            [[-1.5, -2.5, -3.5], -7],
            [[0.1, 0.2, 0.3], 0.7],
            [[0.0000001, 0.0000002], 0.0000004],
            [[0.0000001, 0.0000002, -0.0000001], 0.0000003],
            [['1', '2', '3'], 5],
            [['1.5', '2.5'], 5.0],
            [['-1', '-2', '-3'], -5],
            [['a' => 1, 'b' => 2, 'c' => 3], 5],
            [['a' => 1.5, 'b' => 2.5], 5.0],
            [[1, '2', 3.0], 5],
            [new \ArrayIterator([1, 2, 3]), 5],
            [static fn () => self::get123Generator(), 5],
        ];
    }

    #[DataProvider('getInvalidSumValues')]
    public function testInvalidValuesForExactSum(iterable|callable $values, int|float $sum)
    {
        $constraint = new Sum(exactly: $sum);
        $this->validator->validate(\is_callable($values) ? $values() : $values, $constraint);

        $this->buildViolation($constraint->exactMessage)
            ->setParameter('{{ sum }}', $this->getSum(\is_callable($values) ? $values() : $values))
            ->setParameter('{{ limit }}', $sum)
            ->setInvalidValue(\is_callable($values) ? $values() : $values)
            ->setCode(Sum::NOT_EQUAL_SUM_ERROR)
            ->assertRaised();
    }

    public static function getValidMinSumValues()
    {
        return [
            [[], -1],
            [[1, 2, 3], 6],
            [[1, 2, 3, -4], 2],
            [[-1, -2, -3], -6],
            [[1.5, 2.5, 3], 7],
            [[1.5, 2.5, 3.5], 7.5],
            [[1.5, 2.5, -3.5], 0.5],
            [[-1.5, -2.5, -3.5], -7.5],
            [[0.1, 0.2, 0.3], 0.6],
            [[0.0000001, 0.0000002], 0.0000003],
            [[0.0000001, 0.0000002, -0.0000001], 0.0000002],
            [['1', '2', '3'], 6],
            [['1.5', '2.5'], 4.0],
            [['-1', '-2', '-3'], -6],
            [['a' => 1, 'b' => 2, 'c' => 3], 6],
            [['a' => 1.5, 'b' => 2.5], 4.0],
            [[1, '2', 3.0], 6],
            [new \ArrayIterator([1, 2, 3]), 6],
            [self::get123Generator(), 6],
        ];
    }

    #[DataProvider('getValidMinSumValues')]
    public function testValidValuesForMinSum(iterable|callable $values, int|float $min)
    {
        $constraint = new Sum(min: $min);
        $this->validator->validate(\is_callable($values) ? $values() : $values, $constraint);

        $this->assertNoViolation();
    }

    public static function getInvalidMinSumValues()
    {
        return [
            [[], 1],
            [[1, 2, 3], 7],
            [[1, 2, 3, -4], 3],
            [[-1, -2, -3], -5],
            [[1.5, 2.5, 3], 8],
            [[1.5, 2.5, -3.5], 1],
            [[-1.5, -2.5, -3.5], -7],
            [[0.1, 0.2, 0.3], 0.7],
            [[0.0000001, 0.0000002], 0.0000004],
            [[0.0000001, 0.0000002, -0.0000001], 0.0000003],
            [['1', '2', '3'], 7],
            [['1.5', '2.5'], 5],
            [['-1', '-2', '-3'], -5],
            [['a' => 1, 'b' => 2, 'c' => 3], 7],
            [['a' => 1.5, 'b' => 2.5], 5],
            [[1, '2', 3.0], 7],
            [new \ArrayIterator([1, 2, 3]), 7],
            [static fn () => self::get123Generator(), 7],
        ];
    }

    #[DataProvider('getInvalidMinSumValues')]
    public function testInvalidValuesForMinSum(iterable|callable $values, int|float $min)
    {
        $constraint = new Sum(min: $min);
        $this->validator->validate(\is_callable($values) ? $values() : $values, $constraint);

        $this->buildViolation($constraint->minMessage)
            ->setParameter('{{ sum }}', $this->getSum(\is_callable($values) ? $values() : $values))
            ->setParameter('{{ limit }}', $min)
            ->setInvalidValue(\is_callable($values) ? $values() : $values)
            ->setCode(Sum::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public static function getValidMaxSumValues()
    {
        return [
            [[], 1],
            [[1, 2, 3], 6],
            [[1, 2, 3, -4], 10],
            [[-1, -2, -3], -6],
            [[1.5, 2.5, 3], 7],
            [[1.5, 2.5, 3.5], 8],
            [[1.5, 2.5, -3.5], 8],
            [[-1.5, -2.5, -3.5], -7],
            [[0.1, 0.2, 0.3], 1],
            [[0.0000001, 0.0000002], 0.000001],
            [[0.0000001, 0.0000002, -0.0000001], 0.000001],
            [['1', '2', '3'], 6],
            [['1.5', '2.5'], 5],
            [['-1', '-2', '-3'], -6],
            [['a' => 1, 'b' => 2, 'c' => 3], 6],
            [['a' => 1.5, 'b' => 2.5], 5],
            [[1, '2', 3.0], 6],
            [new \ArrayIterator([1, 2, 3]), 6],
            [self::get123Generator(), 6],
        ];
    }

    #[DataProvider('getValidMaxSumValues')]
    public function testValidValuesForMaxSum(iterable|callable $values, int|float $max)
    {
        $constraint = new Sum(max: $max);
        $this->validator->validate(\is_callable($values) ? $values() : $values, $constraint);

        $this->assertNoViolation();
    }

    public static function getInvalidMaxSumValues()
    {
        return [
            [[], -1],
            [[1, 2, 3], 5],
            [[1, 2, 3, -4], 1],
            [[-1, -2, -3], -7],
            [[1.5, 2.5, 3], 6],
            [[1.5, 2.5, 3.5], 7],
            [[1.5, 2.5, -3.5], -1],
            [[-1.5, -2.5, -3.5], -8],
            [[0.1, 0.2, 0.3], 0.5],
            [[0.0000001, 0.0000002], 0.0000002],
            [[0.0000001, 0.0000002, -0.0000001], 0.0000001],
            [['1', '2', '3'], 5],
            [['1.5', '2.5'], 3.9],
            [['-1', '-2', '-3'], -7],
            [['a' => 1, 'b' => 2, 'c' => 3], 5],
            [['a' => 1.5, 'b' => 2.5], 3.9],
            [[1, '2', 3.0], 5],
            [new \ArrayIterator([1, 2, 3]), 5],
            [static fn () => self::get123Generator(), 5],
        ];
    }

    #[DataProvider('getInvalidMaxSumValues')]
    public function testInvalidValuesForMaxSum(iterable|callable $values, int|float $max)
    {
        $constraint = new Sum(max: $max);
        $this->validator->validate(\is_callable($values) ? $values() : $values, $constraint);

        $this->buildViolation($constraint->maxMessage)
            ->setParameter('{{ sum }}', $this->getSum(\is_callable($values) ? $values() : $values))
            ->setParameter('{{ limit }}', $max)
            ->setInvalidValue(\is_callable($values) ? $values() : $values)
            ->setCode(Sum::TOO_HIGH_ERROR)
            ->assertRaised();
    }
}
