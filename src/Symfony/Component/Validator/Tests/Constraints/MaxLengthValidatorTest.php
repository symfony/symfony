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

use Symfony\Component\Validator\Constraints\MaxLength;
use Symfony\Component\Validator\Constraints\MaxLengthValidator;

class MaxLengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        set_error_handler(array($this, "deprecationErrorHandler"));

        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new MaxLengthValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        restore_error_handler();

        $this->context = null;
        $this->validator = null;
    }

    public function deprecationErrorHandler($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new MaxLength(array('limit' => 5)));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new MaxLength(array('limit' => 5)));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new MaxLength(array('limit' => 5)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new MaxLength(array('limit' => 5));
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array(12345),
            array('12345'),
            array('üüüüü', true),
            array('ééééé', true),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $constraint = new MaxLength(array(
            'limit' => 5,
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ value }}' => (string) $value,
                '{{ limit }}' => 5,
            )), $this->identicalTo($value), 5);

        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array(123456),
            array('123456'),
            array('üüüüüü', true),
            array('éééééé', true),
        );
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new MaxLength(array(
            'limit' => 5,
        ));

        $this->assertEquals('limit', $constraint->getDefaultOption());
    }
}
