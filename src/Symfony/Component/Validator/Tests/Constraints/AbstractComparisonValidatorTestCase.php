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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ComparisonTest_Class
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }
}

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
abstract class AbstractComparisonValidatorTestCase extends ConstraintValidatorTestCase
{
    protected static function addPhp5Dot5Comparisons(array $comparisons)
    {
        $result = $comparisons;

        // Duplicate all tests involving DateTime objects to be tested with
        // DateTimeImmutable objects as well
        foreach ($comparisons as $comparison) {
            $add = false;

            foreach ($comparison as $i => $value) {
                if ($value instanceof \DateTime) {
                    $comparison[$i] = new \DateTimeImmutable($value->format('Y-m-d H:i:s.u e'));
                    $add = true;
                } elseif ('DateTime' === $value) {
                    $comparison[$i] = 'DateTimeImmutable';
                    $add = true;
                }
            }

            if ($add) {
                $result[] = $comparison;
            }
        }

        return $result;
    }

    public static function provideInvalidConstraintOptions()
    {
        return [
            [null],
            [[]],
        ];
    }

    /**
     * @dataProvider provideInvalidConstraintOptions
     */
    public function testThrowsConstraintExceptionIfNoValueOrPropertyPath($options)
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires either the "value" or "propertyPath" option to be set.');
        $this->createConstraint($options);
    }

    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires only one of the "value" or "propertyPath" options to be set, not both.');
        $this->createConstraint([
            'value' => 'value',
            'propertyPath' => 'propertyPath',
        ]);
    }

    /**
     * @dataProvider provideValidComparisonsToPropertyPath
     */
    public function testValidComparisonToPropertyPath($comparedValue)
    {
        $constraint = $this->createConstraint(['propertyPath' => 'value']);

        $object = new ComparisonTest_Class(5);

        $this->setObject($object);

        $this->validator->validate($comparedValue, $constraint);

        $this->assertNoViolation();
    }

    public function testNoViolationOnNullObjectWithPropertyPath()
    {
        $constraint = $this->createConstraint(['propertyPath' => 'propertyPath']);

        $this->setObject(null);

        $this->validator->validate('some data', $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidValuePath()
    {
        $constraint = $this->createConstraint(['propertyPath' => 'foo']);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage(\sprintf('Invalid property path "foo" provided to "%s" constraint', $constraint::class));

        $object = new ComparisonTest_Class(5);

        $this->setObject($object);

        $this->validator->validate(5, $constraint);
    }

    abstract public static function provideValidComparisons(): array;

    abstract public static function provideValidComparisonsToPropertyPath(): array;

    abstract public static function provideInvalidComparisons(): array;

    abstract protected static function createConstraint(?array $options = null): Constraint;

    protected function getErrorCode(): ?string
    {
        return null;
    }
}
