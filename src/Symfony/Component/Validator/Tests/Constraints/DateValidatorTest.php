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

class DateValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new DateValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Date());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Date());
    }

    public function testDateTimeClassIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(new \DateTime(), new Date());
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Date());
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDates($date)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($date, new Date());
    }

    public function getValidDates()
    {
        return array(
            array('2010-01-01'),
            array('1955-12-12'),
            array('2030-05-31'),
        );
    }

    /**
     * @dataProvider getInvalidDates
     */
    public function testInvalidDates($date)
    {
        $constraint = new Date(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $date,
            ));

        $this->validator->validate($date, $constraint);
    }

    public function getInvalidDates()
    {
        return array(
            array('foobar'),
            array('foobar 2010-13-01'),
            array('2010-13-01 foobar'),
            array('2010-13-01'),
            array('2010-04-32'),
            array('2010-02-29'),
        );
    }
}
