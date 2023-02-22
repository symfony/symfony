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

use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AbstractComparison;
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
     * @dataProvider provideAllValidComparisons
     */
    public function testValidComparisonToValue($dirtyValue, $comparisonValue)
    {
        $constraint = $this->createConstraint(['value' => $comparisonValue]);

        $this->validator->validate($dirtyValue, $constraint);

        $this->assertNoViolation();
    }

    public static function provideAllValidComparisons(): array
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $comparisons = self::addPhp5Dot5Comparisons(static::provideValidComparisons());

        date_default_timezone_set($timezone);

        return $comparisons;
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
        $this->expectExceptionMessage(sprintf('Invalid property path "foo" provided to "%s" constraint', $constraint::class));

        $object = new ComparisonTest_Class(5);

        $this->setObject($object);

        $this->validator->validate(5, $constraint);
    }

    abstract public static function provideValidComparisons(): array;

    abstract public static function provideValidComparisonsToPropertyPath(): array;

    /**
     * @dataProvider provideAllInvalidComparisons
     */
    public function testInvalidComparisonToValue($dirtyValue, $dirtyValueAsString, $comparedValue, $comparedValueString, $comparedValueType)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        if ($dirtyValue instanceof \DateTime || $dirtyValue instanceof \DateTimeInterface) {
            IntlTestHelper::requireIntl($this, '57.1');
        }

        $constraint = $this->createConstraint(['value' => $comparedValue]);
        $constraint->message = 'Constraint Message';

        $this->validator->validate($dirtyValue, $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', $dirtyValueAsString)
            ->setParameter('{{ compared_value }}', $comparedValueString)
            ->setParameter('{{ compared_value_type }}', $comparedValueType)
            ->setCode($this->getErrorCode())
            ->assertRaised();
    }

    public function testInvalidComparisonToPropertyPathAddsPathAsParameter()
    {
        [$dirtyValue, $dirtyValueAsString, $comparedValue, $comparedValueString, $comparedValueType] = current($this->provideAllInvalidComparisons());

        $constraint = $this->createConstraint(['propertyPath' => 'value']);
        $constraint->message = 'Constraint Message';

        $object = new ComparisonTest_Class($comparedValue);

        $this->setObject($object);

        $this->validator->validate($dirtyValue, $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', $dirtyValueAsString)
            ->setParameter('{{ compared_value }}', $comparedValueString)
            ->setParameter('{{ compared_value_path }}', 'value')
            ->setParameter('{{ compared_value_type }}', $comparedValueType)
            ->setCode($this->getErrorCode())
            ->assertRaised();
    }

    /**
     * @dataProvider throwsOnInvalidStringDatesProvider
     */
    public function testThrowsOnInvalidStringDates(AbstractComparison $constraint, $expectedMessage, $value)
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->validate($value, $constraint);
    }

    public static function throwsOnInvalidStringDatesProvider(): array
    {
        $constraint = static::createConstraint([
            'value' => 'foo',
        ]);

        $constraintClass = $constraint::class;

        return [
            [$constraint, sprintf('The compared value "foo" could not be converted to a "DateTimeImmutable" instance in the "%s" constraint.', $constraintClass), new \DateTimeImmutable()],
            [$constraint, sprintf('The compared value "foo" could not be converted to a "DateTime" instance in the "%s" constraint.', $constraintClass), new \DateTime()],
        ];
    }

    /**
     * @dataProvider provideComparisonsToNullValueAtPropertyPath
     */
    public function testCompareWithNullValueAtPropertyAt($dirtyValue, $dirtyValueAsString, $isValid)
    {
        $constraint = $this->createConstraint(['propertyPath' => 'value']);
        $constraint->message = 'Constraint Message';

        $object = new ComparisonTest_Class(null);
        $this->setObject($object);

        $this->validator->validate($dirtyValue, $constraint);

        if ($isValid) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation('Constraint Message')
                ->setParameter('{{ value }}', $dirtyValueAsString)
                ->setParameter('{{ compared_value }}', 'null')
                ->setParameter('{{ compared_value_type }}', 'null')
                ->setParameter('{{ compared_value_path }}', 'value')
                ->setCode($this->getErrorCode())
                ->assertRaised();
        }
    }

    public static function provideAllInvalidComparisons(): array
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $comparisons = self::addPhp5Dot5Comparisons(static::provideInvalidComparisons());

        date_default_timezone_set($timezone);

        return $comparisons;
    }

    abstract public static function provideInvalidComparisons(): array;

    abstract public static function provideComparisonsToNullValueAtPropertyPath();

    abstract protected static function createConstraint(array $options = null): Constraint;

    protected function getErrorCode(): ?string
    {
        return null;
    }
}
