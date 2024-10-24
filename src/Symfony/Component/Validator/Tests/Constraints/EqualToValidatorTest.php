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
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\EqualToValidator;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\TypedDummy;
use Symfony\Component\Validator\Tests\IcuCompatibilityTrait;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class EqualToValidatorTest extends AbstractComparisonValidatorTestCase
{
    use IcuCompatibilityTrait;
    use InvalidComparisonToValueTestTrait;
    use ThrowsOnInvalidStringDatesTestTrait;
    use ValidComparisonToValueTrait;

    protected function createValidator(): EqualToValidator
    {
        return new EqualToValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        if (null !== $options) {
            return new EqualTo(...$options);
        }

        return new EqualTo();
    }

    protected function getErrorCode(): ?string
    {
        return EqualTo::NOT_EQUAL_ERROR;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [3, 3],
            [3, '3'],
            ['a', 'a'],
            [new \DateTime('2000-01-01'), new \DateTime('2000-01-01')],
            [new \DateTime('2000-01-01'), '2000-01-01'],
            [new \DateTime('2000-01-01 UTC'), '2000-01-01 UTC'],
            [new ComparisonTest_Class(5), new ComparisonTest_Class(5)],
            [null, 1],
        ];
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
            ['22', '"22"', '333', '"333"', 'string'],
            [new \DateTime('2001-01-01'), self::normalizeIcuSpaces("Jan 1, 2001, 12:00\u{202F}AM"), new \DateTime('2000-01-01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2001-01-01'), self::normalizeIcuSpaces("Jan 1, 2001, 12:00\u{202F}AM"), '2000-01-01', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2001-01-01 UTC'), self::normalizeIcuSpaces("Jan 1, 2001, 12:00\u{202F}AM"), '2000-01-01 UTC', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
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
