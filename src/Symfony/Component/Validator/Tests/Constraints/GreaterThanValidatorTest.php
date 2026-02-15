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

use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;
use Symfony\Component\Validator\Tests\IcuCompatibilityTrait;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class GreaterThanValidatorTest extends AbstractComparisonValidatorTestCase
{
    use CompareWithNullValueAtPropertyAtTestTrait;
    use IcuCompatibilityTrait;
    use InvalidComparisonToValueTestTrait;
    use ThrowsOnInvalidStringDatesTestTrait;
    use ValidComparisonToValueTrait;

    protected function createValidator(): GreaterThanValidator
    {
        return new GreaterThanValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        if (null !== $options) {
            return new GreaterThan(...$options);
        }

        return new GreaterThan();
    }

    protected function getErrorCode(): ?string
    {
        return GreaterThan::TOO_LOW_ERROR;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [2, 1],
            [new \DateTime('2005/01/01'), new \DateTime('2001/01/01')],
            [new \DateTime('2005/01/01'), '2001/01/01'],
            [new \DateTime('2005/01/01 UTC'), '2001/01/01 UTC'],
            [new ComparisonTest_Class(5), new ComparisonTest_Class(4)],
            ['333', '22'],
            [null, 1],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [6],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [1, '1', 2, '2', 'int'],
            [2, '2', 2, '2', 'int'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), new \DateTime('2005/01/01'), self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2005/01/01', self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2000/01/01', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2005/01/01 UTC', self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2000/01/01 UTC', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new ComparisonTest_Class(4), '4', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            [new ComparisonTest_Class(5), '5', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            ['22', '"22"', '333', '"333"', 'string'],
            ['22', '"22"', '22', '"22"', 'string'],
        ];
    }

    public function testValidRelativeDateWithMockClock()
    {
        $clock = new MockClock('2025-01-15 00:00:00 UTC');
        $this->validator = new GreaterThanValidator(null, $clock);
        $this->validator->initialize($this->context);

        // Value is 20 days after the frozen "now", compared to "-10 days" (Jan 5)
        $value = new \DateTimeImmutable('2025-01-20 00:00:00 UTC');
        $constraint = new GreaterThan('-10 days');

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidRelativeDateWithMockClock()
    {
        $clock = new MockClock('2025-01-15 00:00:00 UTC');
        $this->validator = new GreaterThanValidator(null, $clock);
        $this->validator->initialize($this->context);

        // Value (Jan 1) is before the frozen "now" (Jan 15) minus 10 days (Jan 5)
        $value = new \DateTimeImmutable('2025-01-01 00:00:00 UTC');
        $constraint = new GreaterThan(value: '-10 days', message: 'myMessage');

        $this->validator->validate($value, $constraint);

        $comparedValue = $clock->now()->modify('-10 days');

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', self::formatDateTime($value))
            ->setParameter('{{ compared_value }}', self::formatDateTime($comparedValue))
            ->setParameter('{{ compared_value_type }}', \DateTimeImmutable::class)
            ->setCode(GreaterThan::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testAbsoluteDateWithMockClock()
    {
        $clock = new MockClock('2025-01-15 00:00:00 UTC');
        $this->validator = new GreaterThanValidator(null, $clock);

        // Absolute dates should still work with a mock clock
        $value = new \DateTimeImmutable('2025-06-01 00:00:00 UTC');
        $constraint = new GreaterThan('2025-01-01');

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function testBackwardCompatWithoutClock()
    {
        // Without setClock(), the validator should still work (falls back to system clock)
        $value = new \DateTimeImmutable('2000-01-01 UTC');
        $constraint = new GreaterThan('1999-01-01');

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    private static function formatDateTime(\DateTimeInterface $value): string
    {
        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(\Locale::getDefault(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC');

            return $formatter->format(new \DateTimeImmutable(
                $value->format('Y-m-d H:i:s.u'),
                new \DateTimeZone('UTC')
            ));
        }

        return $value->format('Y-m-d H:i:s');
    }
}
