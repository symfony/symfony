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

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotBlankValidator;

class NotBlankValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new NotBlankValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($value, new NotBlank());
    }

    public function getValidValues()
    {
        return array(
            array('foobar'),
            array(0),
            array(0.0),
            array('0'),
            array(1234),
        );
    }

    public function testNullIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->validator->validate(null, $constraint);
    }

    public function testBlankIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->validator->validate('', $constraint);
    }

    public function testFalseIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->validator->validate(false, $constraint);
    }

    public function testEmptyArrayIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->validator->validate(array(), $constraint);
    }
}
