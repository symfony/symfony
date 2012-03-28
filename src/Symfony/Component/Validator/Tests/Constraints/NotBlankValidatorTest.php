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
    public function testValidValues($date)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->assertTrue($this->validator->isValid($date, new NotBlank()));
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

        $this->assertFalse($this->validator->isValid(null, $constraint));
    }

    public function testBlankIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->assertFalse($this->validator->isValid('', $constraint));
    }

    public function testFalseIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->assertFalse($this->validator->isValid(false, $constraint));
    }

    public function testEmptyArrayIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->assertFalse($this->validator->isValid(array(), $constraint));
    }
}

