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

use Symfony\Component\Validator\Constraints\Time;
use Symfony\Component\Validator\Constraints\TimeValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class TimeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): TimeValidator
    {
        return new TimeValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Time());

        $this->assertNoViolation();
    }

    public function testDefaultWithSeconds()
    {
        $this->validator->validate('10:15:25', new Time());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Time());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Time());
    }

    /**
     * @dataProvider getValidTimes
     */
    public function testValidTimes($time)
    {
        $this->validator->validate($time, new Time());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidTimes
     */
    public function testValidTimesWithNewLine(string $time)
    {
        $this->validator->validate($time."\n", new Time());

        $this->buildViolation('This value is not a valid time.')
            ->setParameter('{{ value }}', '"'.$time."\n".'"')
            ->setCode(Time::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getValidTimes()
    {
        return [
            ['01:02:03'],
            ['00:00:00'],
            ['23:59:59'],
        ];
    }

    /**
     * @dataProvider getValidTimesWithoutSeconds
     */
    public function testValidTimesWithoutSeconds(string $time)
    {
        $this->validator->validate($time, new Time([
            'withSeconds' => false,
        ]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidTimesWithoutSeconds
     */
    public function testValidTimesWithoutSecondsWithNewLine(string $time)
    {
        $this->validator->validate($time."\n", new Time(withSeconds: false));

        $this->buildViolation('This value is not a valid time.')
            ->setParameter('{{ value }}', '"'.$time."\n".'"')
            ->setCode(Time::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getValidTimesWithoutSeconds()
    {
        return [
            ['01:02'],
            ['00:00'],
            ['23:59'],
        ];
    }

    /**
     * @dataProvider getInvalidTimesWithoutSeconds
     */
    public function testInvalidTimesWithoutSeconds(string $time)
    {
        $this->validator->validate($time, $constraint = new Time());

        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"'.$time.'"')
            ->setCode(Time::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidTimesWithoutSeconds()
    {
        return [
            ['01:02'],
            ['00:00'],
            ['23:59'],
        ];
    }

    /**
     * @dataProvider getInvalidTimes
     */
    public function testInvalidTimes($time, $code)
    {
        $constraint = new Time([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($time, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$time.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public static function getInvalidTimes()
    {
        return [
            ['foobar', Time::INVALID_FORMAT_ERROR],
            ['foobar 12:34:56', Time::INVALID_FORMAT_ERROR],
            ['12:34:56 foobar', Time::INVALID_FORMAT_ERROR],
            ['00:00', Time::INVALID_FORMAT_ERROR],
            ['24:00:00', Time::INVALID_TIME_ERROR],
            ['00:60:00', Time::INVALID_TIME_ERROR],
            ['00:00:60', Time::INVALID_TIME_ERROR],
        ];
    }
}
