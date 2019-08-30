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
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\RangeValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class RangeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new RangeValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Range(['min' => 10, 'max' => 20]));

        $this->assertNoViolation();
    }

    public function getTenToTwenty()
    {
        return [
            [10.00001],
            [19.99999],
            ['10.00001'],
            ['19.99999'],
            [10],
            [20],
            [10.0],
            [20.0],
        ];
    }

    public function getLessThanTen()
    {
        return [
            [9.99999, '9.99999'],
            ['9.99999', '"9.99999"'],
            [5, '5'],
            [1.0, '1'],
        ];
    }

    public function getMoreThanTwenty()
    {
        return [
            [20.000001, '20.000001'],
            ['20.000001', '"20.000001"'],
            [21, '21'],
            [30.0, '30'],
        ];
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMin($value)
    {
        $constraint = new Range(['min' => 10]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMax($value)
    {
        $constraint = new Range(['max' => 20]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMinMax($value)
    {
        $constraint = new Range(['min' => 10, 'max' => 20]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesMin($value, $formattedValue)
    {
        $constraint = new Range([
            'min' => 10,
            'minMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 10)
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesMax($value, $formattedValue)
    {
        $constraint = new Range([
            'max' => 20,
            'maxMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 20)
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesCombinedMax($value, $formattedValue)
    {
        $constraint = new Range([
            'min' => 10,
            'max' => 20,
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ min }}', 10)
            ->setParameter('{{ max }}', 20)
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesCombinedMin($value, $formattedValue)
    {
        $constraint = new Range([
            'min' => 10,
            'max' => 20,
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ min }}', 10)
            ->setParameter('{{ max }}', 20)
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    public function getTenthToTwentiethMarch2014()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = [
            [new \DateTime('March 10, 2014')],
            [new \DateTime('March 15, 2014')],
            [new \DateTime('March 20, 2014')],
        ];

        $tests[] = [new \DateTimeImmutable('March 10, 2014')];
        $tests[] = [new \DateTimeImmutable('March 15, 2014')];
        $tests[] = [new \DateTimeImmutable('March 20, 2014')];

        $this->restoreDefaultTimezone();

        return $tests;
    }

    public function getSoonerThanTenthMarch2014()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = [
            [new \DateTime('March 20, 2013'), 'Mar 20, 2013, 12:00 AM'],
            [new \DateTime('March 9, 2014'), 'Mar 9, 2014, 12:00 AM'],
        ];

        $tests[] = [new \DateTimeImmutable('March 20, 2013'), 'Mar 20, 2013, 12:00 AM'];
        $tests[] = [new \DateTimeImmutable('March 9, 2014'), 'Mar 9, 2014, 12:00 AM'];

        $this->restoreDefaultTimezone();

        return $tests;
    }

    public function getLaterThanTwentiethMarch2014()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = [
            [new \DateTime('March 21, 2014'), 'Mar 21, 2014, 12:00 AM'],
            [new \DateTime('March 9, 2015'), 'Mar 9, 2015, 12:00 AM'],
        ];

        $tests[] = [new \DateTimeImmutable('March 21, 2014'), 'Mar 21, 2014, 12:00 AM'];
        $tests[] = [new \DateTimeImmutable('March 9, 2015'), 'Mar 9, 2015, 12:00 AM'];

        $this->restoreDefaultTimezone();

        return $tests;
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMin($value)
    {
        $constraint = new Range(['min' => 'March 10, 2014']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMax($value)
    {
        $constraint = new Range(['max' => 'March 20, 2014']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMinMax($value)
    {
        $constraint = new Range(['min' => 'March 10, 2014', 'max' => 'March 20, 2014']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getSoonerThanTenthMarch2014
     */
    public function testInvalidDatesMin($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $constraint = new Range([
            'min' => 'March 10, 2014',
            'minMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 10, 2014, 12:00 AM')
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLaterThanTwentiethMarch2014
     */
    public function testInvalidDatesMax($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $constraint = new Range([
            'max' => 'March 20, 2014',
            'maxMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 20, 2014, 12:00 AM')
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLaterThanTwentiethMarch2014
     */
    public function testInvalidDatesCombinedMax($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $constraint = new Range([
            'min' => 'March 10, 2014',
            'max' => 'March 20, 2014',
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ min }}', 'Mar 10, 2014, 12:00 AM')
            ->setParameter('{{ max }}', 'Mar 20, 2014, 12:00 AM')
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getSoonerThanTenthMarch2014
     */
    public function testInvalidDatesCombinedMin($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $constraint = new Range([
            'min' => 'March 10, 2014',
            'max' => 'March 20, 2014',
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ min }}', 'Mar 10, 2014, 12:00 AM')
            ->setParameter('{{ max }}', 'Mar 20, 2014, 12:00 AM')
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    public function getInvalidValues()
    {
        return [
            [9.999999],
            [20.000001],
            ['9.999999'],
            ['20.000001'],
            [new \stdClass()],
        ];
    }

    public function testNonNumeric()
    {
        $this->validator->validate('abcd', new Range([
            'min' => 10,
            'max' => 20,
            'invalidMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"abcd"')
            ->setCode(Range::INVALID_CHARACTERS_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider throwsOnInvalidStringDatesProvider
     */
    public function testThrowsOnInvalidStringDates($expectedMessage, $value, $min, $max)
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->validate($value, new Range([
            'min' => $min,
            'max' => $max,
        ]));
    }

    public function throwsOnInvalidStringDatesProvider(): array
    {
        return [
            ['The min value "foo" could not be converted to a "DateTimeImmutable" instance in the "Symfony\Component\Validator\Constraints\Range" constraint.', new \DateTimeImmutable(), 'foo', null],
            ['The min value "foo" could not be converted to a "DateTime" instance in the "Symfony\Component\Validator\Constraints\Range" constraint.', new \DateTime(), 'foo', null],
            ['The max value "foo" could not be converted to a "DateTimeImmutable" instance in the "Symfony\Component\Validator\Constraints\Range" constraint.', new \DateTimeImmutable(), null, 'foo'],
            ['The max value "foo" could not be converted to a "DateTime" instance in the "Symfony\Component\Validator\Constraints\Range" constraint.', new \DateTime(), null, 'foo'],
            ['The min value "bar" could not be converted to a "DateTimeImmutable" instance in the "Symfony\Component\Validator\Constraints\Range" constraint.', new \DateTimeImmutable(), 'bar', 'ccc'],
        ];
    }

    public function testNoViolationOnNullObjectWithPropertyPaths()
    {
        $this->setObject(null);

        $this->validator->validate(1, new Range([
            'minPropertyPath' => 'minPropertyPath',
            'maxPropertyPath' => 'maxPropertyPath',
        ]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMinPropertyPath($value)
    {
        $this->setObject(new Limit(10));

        $this->validator->validate($value, new Range([
            'minPropertyPath' => 'value',
        ]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMaxPropertyPath($value)
    {
        $this->setObject(new Limit(20));

        $this->validator->validate($value, new Range([
            'maxPropertyPath' => 'value',
        ]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMinMaxPropertyPath($value)
    {
        $this->setObject(new MinMax(10, 20));

        $this->validator->validate($value, new Range([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
        ]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesMinPropertyPath($value, $formattedValue)
    {
        $this->setObject(new Limit(10));

        $constraint = new Range([
            'minPropertyPath' => 'value',
            'minMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 10)
            ->setParameter('{{ min_limit_path }}', 'value')
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesMaxPropertyPath($value, $formattedValue)
    {
        $this->setObject(new Limit(20));

        $constraint = new Range([
            'maxPropertyPath' => 'value',
            'maxMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 20)
            ->setParameter('{{ max_limit_path }}', 'value')
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesCombinedMaxPropertyPath($value, $formattedValue)
    {
        $this->setObject(new MinMax(10, 20));

        $constraint = new Range([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ min }}', 10)
            ->setParameter('{{ max }}', 20)
            ->setParameter('{{ max_limit_path }}', 'max')
            ->setParameter('{{ min_limit_path }}', 'min')
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesCombinedMinPropertyPath($value, $formattedValue)
    {
        $this->setObject(new MinMax(10, 20));

        $constraint = new Range([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ min }}', 10)
            ->setParameter('{{ max }}', 20)
            ->setParameter('{{ max_limit_path }}', 'max')
            ->setParameter('{{ min_limit_path }}', 'min')
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testViolationOnNullObjectWithDefinedMin($value, $formattedValue)
    {
        $this->setObject(null);

        $this->validator->validate($value, new Range([
            'min' => 10,
            'maxPropertyPath' => 'max',
            'minMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 10)
            ->setParameter('{{ max_limit_path }}', 'max')
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testViolationOnNullObjectWithDefinedMax($value, $formattedValue)
    {
        $this->setObject(null);

        $this->validator->validate($value, new Range([
            'minPropertyPath' => 'min',
            'max' => 20,
            'maxMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 20)
            ->setParameter('{{ min_limit_path }}', 'min')
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMinPropertyPath($value)
    {
        $this->setObject(new Limit('March 10, 2014'));

        $this->validator->validate($value, new Range(['minPropertyPath' => 'value']));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMaxPropertyPath($value)
    {
        $this->setObject(new Limit('March 20, 2014'));

        $constraint = new Range(['maxPropertyPath' => 'value']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMinMaxPropertyPath($value)
    {
        $this->setObject(new MinMax('March 10, 2014', 'March 20, 2014'));

        $constraint = new Range(['minPropertyPath' => 'min', 'maxPropertyPath' => 'max']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getSoonerThanTenthMarch2014
     */
    public function testInvalidDatesMinPropertyPath($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $this->setObject(new Limit('March 10, 2014'));

        $constraint = new Range([
            'minPropertyPath' => 'value',
            'minMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 10, 2014, 12:00 AM')
            ->setParameter('{{ min_limit_path }}', 'value')
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLaterThanTwentiethMarch2014
     */
    public function testInvalidDatesMaxPropertyPath($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $this->setObject(new Limit('March 20, 2014'));

        $constraint = new Range([
            'maxPropertyPath' => 'value',
            'maxMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 20, 2014, 12:00 AM')
            ->setParameter('{{ max_limit_path }}', 'value')
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLaterThanTwentiethMarch2014
     */
    public function testInvalidDatesCombinedMaxPropertyPath($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $this->setObject(new MinMax('March 10, 2014', 'March 20, 2014'));

        $constraint = new Range([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ min }}', 'Mar 10, 2014, 12:00 AM')
            ->setParameter('{{ max }}', 'Mar 20, 2014, 12:00 AM')
            ->setParameter('{{ max_limit_path }}', 'max')
            ->setParameter('{{ min_limit_path }}', 'min')
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getSoonerThanTenthMarch2014
     */
    public function testInvalidDatesCombinedMinPropertyPath($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $this->setObject(new MinMax('March 10, 2014', 'March 20, 2014'));

        $constraint = new Range([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
            'notInRangeMessage' => 'myNotInRangeMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myNotInRangeMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ min }}', 'Mar 10, 2014, 12:00 AM')
            ->setParameter('{{ max }}', 'Mar 20, 2014, 12:00 AM')
            ->setParameter('{{ max_limit_path }}', 'max')
            ->setParameter('{{ min_limit_path }}', 'min')
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider validDateIntervalsMinOnlyProvider
     */
    public function testValidDateIntervalsMinOnly(\DateInterval $value, $min)
    {
        $this->validator->validate($value, new Range([
            'min' => $min,
        ]));

        $this->assertNoViolation();
    }

    public function validDateIntervalsMinOnlyProvider()
    {
        $negative = new \DateInterval('PT30S');
        $negative->invert = 1;

        return [
            ['30 > 20 (\DateInterval instance)' => new \DateInterval('PT30S'), new \DateInterval('PT20S')],
            ['30 > 20 (string)' => new \DateInterval('PT30S'), '+20 seconds'],
            ['30 === 30' => new \DateInterval('PT30S'), '+30 seconds'],
            ['30 > 0' => new \DateInterval('PT30S'), 0],
            ['-30 > -31' => $negative, '-31 seconds'],
        ];
    }

    /**
     * @dataProvider invalidDateIntervalsMinOnlyProvider
     */
    public function testInvalidDateIntervalsMinOnly(\DateInterval $value, $min, string $expectedValue, string $expectedLimit)
    {
        $this->validator->validate($value, new Range([
            'min' => $min,
            'minMessage' => 'foo',
        ]));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', $expectedValue)
            ->setParameter('{{ limit }}', $expectedLimit)
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function invalidDateIntervalsMinOnlyProvider()
    {
        $negative = new \DateInterval('PT30S');
        $negative->invert = 1;

        return [
            ['30 < 31 (\DateInterval instance)' => new \DateInterval('PT30S'), new \DateInterval('PT31S'), '30 seconds', '31 seconds'],
            ['30 < 31 (string)' => new \DateInterval('PT30S'), '+31 seconds', '30 seconds', '31 seconds'],
            ['-30 < 0' => $negative, 0, '-30 seconds', '0'],
            ['-30 < -29' => $negative, '-29 seconds', '-30 seconds', '-29 seconds'],
        ];
    }

    /**
     * @dataProvider validDateIntervalsMaxOnlyProvider
     */
    public function testValidDateIntervalsMaxOnly(\DateInterval $value, $max)
    {
        $this->validator->validate($value, new Range([
            'max' => $max,
        ]));

        $this->assertNoViolation();
    }

    public function validDateIntervalsMaxOnlyProvider()
    {
        $negative = new \DateInterval('PT30S');
        $negative->invert = 1;

        return [
            ['30 < 31 (\DateInterval instance)' => new \DateInterval('PT30S'), new \DateInterval('PT31S')],
            ['30 < 31 (string)' => new \DateInterval('PT30S'), '+31 seconds'],
            ['30 === 30' => new \DateInterval('PT30S'), '+30 seconds'],
            ['-30 < 0' => $negative, 0],
            ['-30 < -20' => $negative, '-20 seconds'],
        ];
    }

    /**
     * @dataProvider invalidDateIntervalsMaxOnlyProvider
     */
    public function testInvalidDateIntervalsMaxOnly(\DateInterval $value, $max, string $expectedValue, string $expectedLimit)
    {
        $this->validator->validate($value, new Range([
            'max' => $max,
            'maxMessage' => 'foo',
        ]));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', $expectedValue)
            ->setParameter('{{ limit }}', $expectedLimit)
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public function invalidDateIntervalsMaxOnlyProvider()
    {
        $negative = new \DateInterval('PT30S');
        $negative->invert = 1;

        return [
            ['30 > 29 (\DateInterval instance)' => new \DateInterval('PT30S'), new \DateInterval('PT29S'), '30 seconds', '29 seconds'],
            ['30 > 29 (string)' => new \DateInterval('PT30S'), '+29 seconds', '30 seconds', '29 seconds'],
            ['30 > 0' => new \DateInterval('PT30S'), 0, '30 seconds', '0'],
            ['-30 > -31' => $negative, '-31 seconds', '-30 seconds', '-31 seconds'],
        ];
    }

    /**
     * @dataProvider validDateIntervalsCombinedProvider
     */
    public function testValidDateIntervalsCombined(\DateInterval $value, $min, $max)
    {
        $this->validator->validate($value, new Range([
            'min' => $min,
            'max' => $max,
        ]));

        $this->assertNoViolation();
    }

    public function validDateIntervalsCombinedProvider()
    {
        $negative = new \DateInterval('PT30S');
        $negative->invert = 1;

        return [
            ['31 < 30 < 29 (2 \DateInterval instances)' => new \DateInterval('PT30S'), new \DateInterval('PT29S'), new \DateInterval('PT31S')],
            ['31 < 30 < 29 (\DateInterval instances & string)' => new \DateInterval('PT30S'), new \DateInterval('PT29S'), '+31 seconds'],
            ['31 < 30 < 29 (string & \DateInterval instance)' => new \DateInterval('PT30S'), '+29 seconds', new \DateInterval('PT31S')],
            ['31 < 30 < 29 (2 strings)' => new \DateInterval('PT30S'), '+29 seconds', '+31 seconds'],
            ['31 < 30 < 0' => new \DateInterval('PT30S'), 0, '+31 seconds'],
            ['0 < -30 < -31' => $negative, '-31 seconds', 0],
            ['-29 < -30 < -31' => $negative, '-31 seconds', '-29 seconds'],
        ];
    }

    /**
     * @dataProvider invalidDateIntervalsCombinedProvider
     */
    public function testInvalidDateIntervalsCombined(\DateInterval $value, $min, $max, string $expectedValue, string $expectedMin, string $expectedMax)
    {
        $this->validator->validate($value, new Range([
            'min' => $min,
            'max' => $max,
            'notInRangeMessage' => 'foo',
        ]));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', $expectedValue)
            ->setParameter('{{ min }}', $expectedMin)
            ->setParameter('{{ max }}', $expectedMax)
            ->setCode(Range::NOT_IN_RANGE_ERROR)
            ->assertRaised();
    }

    public function invalidDateIntervalsCombinedProvider()
    {
        $negative = new \DateInterval('PT30S');
        $negative->invert = 1;

        return [
            ['30 < 31 && 30 > 29 (2 \DateInterval instances)' => new \DateInterval('PT30S'), new \DateInterval('PT31S'), new \DateInterval('PT29S'), '30 seconds', '31 seconds', '29 seconds'],
            ['30 < 31 && 30 > 29 (\DateInterval instances & string)' => new \DateInterval('PT30S'), new \DateInterval('PT31S'), '+29 seconds', '30 seconds', '31 seconds', '29 seconds'],
            ['30 < 31 && 30 > 29 (string & \DateInterval instance)' => new \DateInterval('PT30S'), '+31 seconds', new \DateInterval('PT29S'),  '30 seconds', '31 seconds', '29 seconds'],
            ['30 < 31 && 30 > 29 (2 strings)' => new \DateInterval('PT30S'), '+31 seconds', '+29 seconds',  '30 seconds', '31 seconds', '29 seconds'],
            ['30 < 31 && 30 > 0' => new \DateInterval('PT30S'), '+31 seconds', 0, '30 seconds', '31 seconds', '0'],
            ['-30 < 0 && -30 > -31' => $negative, 0, '-31 seconds', '-30 seconds', '0', '-31 seconds'],
            ['-30 < -29 && -30 > -31' => $negative, '-29 seconds', '-31 seconds', '-30 seconds', '-29 seconds', '-31 seconds'],
        ];
    }
}

final class Limit
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}

final class MinMax
{
    private $min;
    private $max;

    public function __construct($min, $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function getMin()
    {
        return $this->min;
    }

    public function getMax()
    {
        return $this->max;
    }
}
