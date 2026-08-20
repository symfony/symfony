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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;
use Symfony\Component\Validator\Tests\IcuCompatibilityTrait;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class GreaterThanOrEqualValidatorTest extends AbstractComparisonValidatorTestCase
{
    use CompareWithNullValueAtPropertyAtTestTrait;
    use IcuCompatibilityTrait;
    use InvalidComparisonToValueTestTrait;
    use ThrowsOnInvalidStringDatesTestTrait;
    use ValidComparisonToValueTrait;

    protected function createValidator(): GreaterThanOrEqualValidator
    {
        return new GreaterThanOrEqualValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        if (null !== $options) {
            return new GreaterThanOrEqual(...$options);
        }

        return new GreaterThanOrEqual();
    }

    protected function getErrorCode(): ?string
    {
        return GreaterThanOrEqual::TOO_LOW_ERROR;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [3, 2],
            [1, 1],
            [new \DateTime('2010/01/01'), new \DateTime('2000/01/01')],
            [new \DateTime('2000/01/01'), new \DateTime('2000/01/01')],
            [new \DateTime('2010/01/01'), '2000/01/01'],
            [new \DateTime('2000/01/01'), '2000/01/01'],
            [new \DateTime('2010/01/01 UTC'), '2000/01/01 UTC'],
            [new \DateTime('2000/01/01 UTC'), '2000/01/01 UTC'],
            ['a', 'a'],
            ['z', 'a'],
            [null, 1],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [5],
            [6],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [1, '1', 2, '2', 'int'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), new \DateTime('2005/01/01'), self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2005/01/01', self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2005/01/01 UTC', self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            ['b', '"b"', 'c', '"c"', 'string'],
        ];
    }

    #[RequiresPhpExtension('bcmath')]
    public function testValidBcMathNumberWithStringValue()
    {
        $this->validator->validate(new \BcMath\Number('10.5'), new GreaterThanOrEqual('10'));

        $this->assertNoViolation();
    }

    #[RequiresPhpExtension('bcmath')]
    public function testValidBcMathNumberWithIntValue()
    {
        $this->validator->validate(new \BcMath\Number('10.5'), new GreaterThanOrEqual(10));

        $this->assertNoViolation();
    }

    #[RequiresPhpExtension('bcmath')]
    public function testInvalidBcMathNumberFormatsLimitWithoutQuotes()
    {
        $this->validator->validate(new \BcMath\Number('9.5'), new GreaterThanOrEqual(value: '10', message: 'myMessage'));

        // The compared string limit must be rendered as "10", not the quoted
        // string "\"10\"" that a plain string comparison would produce.
        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '9.5')
            ->setParameter('{{ compared_value }}', '10')
            ->setParameter('{{ compared_value_type }}', 'BcMath\Number')
            ->setCode(GreaterThanOrEqual::TOO_LOW_ERROR)
            ->assertRaised();
    }

    #[RequiresPhpExtension('bcmath')]
    public function testBcMathNumberComparesWithFullPrecision()
    {
        $this->validator->validate(new \BcMath\Number('9.9999999999'), new GreaterThanOrEqual(value: 10, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '9.9999999999')
            ->setParameter('{{ compared_value }}', '10')
            ->setParameter('{{ compared_value_type }}', 'BcMath\Number')
            ->setCode(GreaterThanOrEqual::TOO_LOW_ERROR)
            ->assertRaised();
    }

    #[RequiresPhpExtension('bcmath')]
    public function testBcMathNumberWithFloatValue()
    {
        // PHP truncates the float to an integer when comparing it with a BcMath\Number,
        // which would make 10.2 greater than 10.5
        $this->validator->validate(new \BcMath\Number('10.2'), new GreaterThanOrEqual(value: 10.5, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '10.2')
            ->setParameter('{{ compared_value }}', '10.5')
            ->setParameter('{{ compared_value_type }}', 'BcMath\Number')
            ->setCode(GreaterThanOrEqual::TOO_LOW_ERROR)
            ->assertRaised();
    }

    #[RequiresPhpExtension('bcmath')]
    public function testBcMathNumberWithSmallFloatValue()
    {
        $this->validator->validate(new \BcMath\Number('0.00000005'), new GreaterThanOrEqual(value: 0.0000001, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '0.00000005')
            ->setParameter('{{ compared_value }}', '0.0000001')
            ->setParameter('{{ compared_value_type }}', 'BcMath\Number')
            ->setCode(GreaterThanOrEqual::TOO_LOW_ERROR)
            ->assertRaised();
    }

    #[RequiresPhpExtension('bcmath')]
    public function testBcMathNumberWithExponentNotationStringValue()
    {
        $this->validator->validate(new \BcMath\Number('0.00000005'), new GreaterThanOrEqual(value: '1.0E-7', message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '0.00000005')
            ->setParameter('{{ compared_value }}', '0.0000001')
            ->setParameter('{{ compared_value_type }}', 'BcMath\Number')
            ->setCode(GreaterThanOrEqual::TOO_LOW_ERROR)
            ->assertRaised();
    }
}
