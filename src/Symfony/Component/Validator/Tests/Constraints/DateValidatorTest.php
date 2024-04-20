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

use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class DateValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): DateValidator
    {
        return new DateValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Date());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Date());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Date());
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDates($date)
    {
        $this->validator->validate($date, new Date());

        $this->assertNoViolation();
    }

    public static function getValidDates()
    {
        return [
            ['2010-01-01'],
            ['1955-12-12'],
            ['2030-05-31'],
        ];
    }

    /**
     * @dataProvider getInvalidDates
     */
    public function testInvalidDates($date, $code)
    {
        $constraint = new Date([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($date, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$date.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function testInvalidDateNamed()
    {
        $constraint = new Date(message: 'myMessage');

        $this->validator->validate('foobar', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"foobar"')
            ->setCode(Date::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidDates()
    {
        return [
            ['foobar', Date::INVALID_FORMAT_ERROR],
            ['foobar 2010-13-01', Date::INVALID_FORMAT_ERROR],
            ['2010-13-01 foobar', Date::INVALID_FORMAT_ERROR],
            ['2010-13-01', Date::INVALID_DATE_ERROR],
            ['2010-04-32', Date::INVALID_DATE_ERROR],
            ['2010-02-29', Date::INVALID_DATE_ERROR],
        ];
    }
}
