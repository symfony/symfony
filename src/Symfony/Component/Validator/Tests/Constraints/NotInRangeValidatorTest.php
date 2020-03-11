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
use Symfony\Component\Validator\Constraints\NotInRange;
use Symfony\Component\Validator\Constraints\NotInRangeValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class NotInRangeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new NotInRangeValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new NotInRange(['min' => 10, 'max' => 20]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getLessThanTenAndMoreThanTwenty
     */
    public function testValidNumericValues($value)
    {
        $constraint = new NotInRange(['min' => 10, 'max' => 20]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function getLessThanTenAndMoreThanTwenty()
    {
        return [
            [9.99999],
            ['9.99999'],
            [5],
            [1.0],
            [20.000001],
            ['20.000001'],
            [21],
            [30.0],
        ];
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testInvalidNumericValues($value, $formattedValue)
    {
        $constraint = new NotInRange([
            'min' => 10,
            'max' => 20,
            'inRangeMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ min }}', 10)
            ->setParameter('{{ max }}', 20)
            ->setCode(NotInRange::IN_RANGE_ERROR)
            ->assertRaised();
    }

    public function getTenToTwenty()
    {
        return [
            [10.00001, '10.00001'],
            [19.99999, '19.99999'],
            ['10.00001', '"10.00001"'],
            ['19.99999', '"19.99999"'],
            [10, '10'],
            [20, '20'],
            [10.0, '10'],
            [20.0, '20'],
        ];
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDates($value)
    {
        $constraint = new NotInRange(['min' => 'March 10, 2014', 'max' => 'March 20, 2014']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function getValidDates()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = [
            [new \DateTime('March 20, 2013')],
            [new \DateTime('March 9, 2014')],
            [new \DateTime('March 21, 2014')],
            [new \DateTime('March 9, 2015')],
        ];

        $tests[] = [new \DateTimeImmutable('March 20, 2013')];
        $tests[] = [new \DateTimeImmutable('March 9, 2014')];
        $tests[] = [new \DateTimeImmutable('March 21, 2014')];
        $tests[] = [new \DateTimeImmutable('March 9, 2015')];

        $this->restoreDefaultTimezone();

        return $tests;
    }

    /**
     * @dataProvider getInvalidDates
     */
    public function testInvalidDates($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $constraint = new NotInRange([
            'min' => 'March 10, 2014',
            'max' => 'March 20, 2014',
            'inRangeMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ min }}', 'Mar 10, 2014, 12:00 AM')
            ->setParameter('{{ max }}', 'Mar 20, 2014, 12:00 AM')
            ->setCode(NotInRange::IN_RANGE_ERROR)
            ->assertRaised();
    }

    public function getInvalidDates()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = [
            [new \DateTime('March 10, 2014'), 'Mar 10, 2014, 12:00 AM'],
            [new \DateTime('March 15, 2014'), 'Mar 15, 2014, 12:00 AM'],
            [new \DateTime('March 20, 2014'), 'Mar 20, 2014, 12:00 AM'],
        ];

        $tests[] = [new \DateTimeImmutable('March 10, 2014'), 'Mar 10, 2014, 12:00 AM'];
        $tests[] = [new \DateTimeImmutable('March 15, 2014'), 'Mar 15, 2014, 12:00 AM'];
        $tests[] = [new \DateTimeImmutable('March 20, 2014'), 'Mar 20, 2014, 12:00 AM'];

        $this->restoreDefaultTimezone();

        return $tests;
    }

    public function testNonNumeric()
    {
        $this->validator->validate('abcd', new NotInRange([
            'min' => 10,
            'max' => 20,
            'invalidMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"abcd"')
            ->setCode(NotInRange::INVALID_CHARACTERS_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider throwsOnInvalidStringDatesProvider
     */
    public function testThrowsOnInvalidStringDates($expectedMessage, $value, $min, $max)
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->validate($value, new NotInRange([
            'min' => $min,
            'max' => $max,
        ]));
    }

    public function throwsOnInvalidStringDatesProvider(): array
    {
        return [
            ['The max value "foo" could not be converted to a "DateTime" instance in the "Symfony\Component\Validator\Constraints\NotInRange" constraint.', new \DateTime(), 'first day of January', 'foo'],
            ['The min value "bar" could not be converted to a "DateTimeImmutable" instance in the "Symfony\Component\Validator\Constraints\NotInRange" constraint.', new \DateTimeImmutable(), 'bar', 'ccc'],
        ];
    }

    /**
     * @dataProvider getLessThanTenAndMoreThanTwenty
     */
    public function testValidValuesPropertyPath($value)
    {
        $this->setObject(new MinMax(10, 20));

        $this->validator->validate($value, new NotInRange([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
        ]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testInvalidValuesPropertyPath($value, $formattedValue)
    {
        $this->setObject(new MinMax(10, 20));

        $constraint = new NotInRange([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
            'inRangeMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ min_limit_path }}', 'min')
            ->setParameter('{{ min }}', 10)
            ->setParameter('{{ max_limit_path }}', 'max')
            ->setParameter('{{ max }}', 20)
            ->setCode(NotInRange::IN_RANGE_ERROR)
            ->assertRaised();
    }

    public function testThrowsOnNullObjectWithPropertyPaths()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The min value cannot be null in the "Symfony\Component\Validator\Constraints\NotInRange" constraint.');

        $this->setObject(null);

        $this->validator->validate(1, new NotInRange([
            'minPropertyPath' => 'minPropertyPath',
            'maxPropertyPath' => 'maxPropertyPath',
        ]));
    }

    public function testThrowsOnNullObjectWithDefinedMin()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The max value cannot be null in the "Symfony\Component\Validator\Constraints\NotInRange" constraint.');

        $this->setObject(null);

        $this->validator->validate(1, new NotInRange([
            'min' => 10,
            'maxPropertyPath' => 'max',
        ]));
    }

    public function testThrowsOnNullObjectWithDefinedMax()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The min value cannot be null in the "Symfony\Component\Validator\Constraints\NotInRange" constraint.');

        $this->setObject(null);

        $this->validator->validate(1, new NotInRange([
            'minPropertyPath' => 'min',
            'max' => 5,
        ]));
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDatesPropertyPath($value)
    {
        $this->setObject(new MinMax('March 10, 2014', 'March 20, 2014'));

        $constraint = new NotInRange(['minPropertyPath' => 'min', 'maxPropertyPath' => 'max']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidDates
     */
    public function testInvalidDatesPropertyPath($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this, '57.1');

        $this->setObject(new MinMax('March 10, 2014', 'March 20, 2014'));

        $constraint = new NotInRange([
            'minPropertyPath' => 'min',
            'maxPropertyPath' => 'max',
            'inRangeMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ min }}', 'Mar 10, 2014, 12:00 AM')
            ->setParameter('{{ max }}', 'Mar 20, 2014, 12:00 AM')
            ->setParameter('{{ max_limit_path }}', 'max')
            ->setParameter('{{ min_limit_path }}', 'min')
            ->setCode(NotInRange::IN_RANGE_ERROR)
            ->assertRaised();
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
