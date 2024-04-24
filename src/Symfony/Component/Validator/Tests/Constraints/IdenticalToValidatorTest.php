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
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\IdenticalToValidator;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\TypedDummy;
use Symfony\Component\Validator\Tests\IcuCompatibilityTrait;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class IdenticalToValidatorTest extends AbstractComparisonValidatorTestCase
{
    use IcuCompatibilityTrait;
    use InvalidComparisonToValueTestTrait;
    use ThrowsOnInvalidStringDatesTestTrait;
    use ValidComparisonToValueTrait;

    protected function createValidator(): IdenticalToValidator
    {
        return new IdenticalToValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        if (null !== $options) {
            return new IdenticalTo(...$options);
        }

        return new IdenticalTo();
    }

    protected function getErrorCode(): ?string
    {
        return IdenticalTo::NOT_IDENTICAL_ERROR;
    }

    public static function provideAllValidComparisons(): array
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        // Don't call addPhp5Dot5Comparisons() automatically, as it does
        // not take care of identical objects
        $comparisons = self::provideValidComparisons();

        date_default_timezone_set($timezone);

        return $comparisons;
    }

    public static function provideValidComparisons(): array
    {
        $date = new \DateTime('2000-01-01');
        $object = new ComparisonTest_Class(2);

        $comparisons = [
            [3, 3],
            ['a', 'a'],
            [$date, $date],
            [$object, $object],
            [null, 1],
        ];

        $immutableDate = new \DateTimeImmutable('2000-01-01');
        $comparisons[] = [$immutableDate, $immutableDate];

        return $comparisons;
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [5],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [1, '1', 2, '2', 'int'],
            [2, '2', '2', '"2"', 'string'],
            ['22', '"22"', '333', '"333"', 'string'],
            [new \DateTime('2001-01-01'), self::normalizeIcuSpaces("Jan 1, 2001, 12:00\u{202F}AM"), new \DateTime('2001-01-01'), self::normalizeIcuSpaces("Jan 1, 2001, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2001-01-01'), self::normalizeIcuSpaces("Jan 1, 2001, 12:00\u{202F}AM"), new \DateTime('1999-01-01'), self::normalizeIcuSpaces("Jan 1, 1999, 12:00\u{202F}AM"), 'DateTime'],
            [new ComparisonTest_Class(4), '4', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
        ];
    }

    public function testCompareWithNullValueAtPropertyAt()
    {
        $constraint = $this->createConstraint(['propertyPath' => 'value']);
        $constraint->message = 'Constraint Message';

        $object = new ComparisonTest_Class(null);
        $this->setObject($object);

        $this->validator->validate(5, $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', '5')
            ->setParameter('{{ compared_value }}', 'null')
            ->setParameter('{{ compared_value_type }}', 'null')
            ->setParameter('{{ compared_value_path }}', 'value')
            ->setCode($this->getErrorCode())
            ->assertRaised();
    }

    public function testCompareWithUninitializedPropertyAtPropertyPath()
    {
        $this->setObject(new TypedDummy());

        $this->validator->validate(5, $this->createConstraint([
            'message' => 'Constraint Message',
            'propertyPath' => 'value',
        ]));

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', '5')
            ->setParameter('{{ compared_value }}', 'null')
            ->setParameter('{{ compared_value_type }}', 'null')
            ->setParameter('{{ compared_value_path }}', 'value')
            ->setCode($this->getErrorCode())
            ->assertRaised();
    }
}
