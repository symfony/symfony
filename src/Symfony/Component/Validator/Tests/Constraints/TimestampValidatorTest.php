<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Timestamp;
use Symfony\Component\Validator\Constraints\TimestampValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class TimestampValidatorTest extends ConstraintValidatorTestCase
{

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new TimestampValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Timestamp());

        $this->assertNoViolation();
    }

    public function testNegativeNumberIsInvalid()
    {
        $this->validator->validate(-1, new Timestamp([
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', -1)
            ->setInvalidValue(-1)
            ->setCode(Timestamp::INVALID_TIMESTAMP_ERROR)
            ->assertRaised();
    }

    public function getGreaterThan()
    {
        return [
            [1719610882, '@1718210882', "Jun 12, 2024, 4:48\u{202F}PM"],
        ];
    }

    public function getLessThan()
    {
        return [
            [1718210882, '@1719610882', "Jun 28, 2024, 9:41\u{202F}PM"],
        ];
    }

    public static function getGreaterThanOrEqual()
    {
        return [
            [1719610882, '@1719610882', "Jun 28, 2024, 9:41\u{202F}PM"],
            [1719610882, '@1718210882', "Jun 12, 2024, 4:48\u{202F}PM"],
        ];
    }

    public static function getLessThanOrEqual()
    {
        return [
            [1719610882, '@1719610882', "Jun 28, 2024, 9:41\u{202F}PM"],
            [1718210882, '@1719610882', "Jun 28, 2024, 9:41\u{202F}PM"],
        ];
    }

    /**
     * @dataProvider getGreaterThan
     */
    public function testValidValueGreaterThan(int $value, string $greaterThan)
    {
        $constraint = new Timestamp(['greaterThan' => $greaterThan]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getGreaterThanOrEqual
     */
    public function testValidValueGreaterThanOrEqualTo(int $value, string $greaterThanOrEqual)
    {
        $constraint = new Timestamp(['greaterThanOrEqual' => $greaterThanOrEqual]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getLessThan
     */
    public function testValidValueLessThan(int $value, string $lessThan)
    {
        $constraint = new Timestamp(['lessThan' => $lessThan]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getLessThanOrEqual
     */
    public function testValidValueLessThanOrEqualTo(int $value, string $lessThanOrEqual)
    {
        $constraint = new Timestamp(['lessThanOrEqual' => $lessThanOrEqual]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getLessThanOrEqual
     */
    public function testInvalidValueGreaterThan(int $value, string $greaterThan, string $comparedValue)
    {
        $constraint = new Timestamp([
            'greaterThan' => $greaterThan,
            'greaterThanMessage' => 'myMessage',
        ]);
        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $value)
            ->setParameter('{{ compared_value }}', $comparedValue)
            ->setInvalidValue($value)
            ->setCode(Timestamp::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLessThan
     */
    public function testInvalidValueGreaterThanOrEqual(int $value, string $greaterThan, string $comparedValue)
    {
        $constraint = new Timestamp([
            'greaterThanOrEqual' => $greaterThan,
            'greaterThanOrEqualMessage' => 'myMessage',
        ]);
        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $value)
            ->setParameter('{{ compared_value }}', $comparedValue)
            ->setInvalidValue($value)
            ->setCode(Timestamp::TOO_LOW_ERROR)
            ->assertRaised();
    }


    /**
     * @dataProvider getGreaterThanOrEqual
     */
    public function testInvalidValueLessThan(int $value, string $lessThan, string $comparedValue)
    {
        $constraint = new Timestamp([
            'lessThan' => $lessThan,
            'lessThanMessage' => 'myMessage',
        ]);
        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $value)
            ->setParameter('{{ compared_value }}', $comparedValue)
            ->setInvalidValue($value)
            ->setCode(Timestamp::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getGreaterThan
     */
    public function testInvalidValueLessThanOrEqual(int $value, string $lessThanOrEqual, string $comparedValue)
    {
        $constraint = new Timestamp([
            'lessThanOrEqual' => $lessThanOrEqual,
            'lessThanOrEqualMessage' => 'myMessage',
        ]);
        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $value)
            ->setParameter('{{ compared_value }}', $comparedValue)
            ->setInvalidValue($value)
            ->setCode(Timestamp::TOO_HIGH_ERROR)
            ->assertRaised();
    }
}