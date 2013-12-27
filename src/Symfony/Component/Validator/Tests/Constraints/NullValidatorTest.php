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

use Symfony\Component\Validator\Constraints\Null;
use Symfony\Component\Validator\Constraints\NullValidator;

class NullValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new NullValidator();
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

        $this->validator->validate(null, new Null());
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $readableValue)
    {
        $constraint = new Null(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $readableValue,
            ));

        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array(0, 0),
            array(false, false),
            array(true, true),
            array('', ''),
            array('foo bar', 'foo bar'),
            array(new \DateTime(), 'DateTime'),
            array(array(), 'Array'),
        );
    }
}
