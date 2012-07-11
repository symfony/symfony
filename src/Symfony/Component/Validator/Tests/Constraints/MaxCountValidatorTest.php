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

use Symfony\Component\Validator\Constraints\MaxCount;
use Symfony\Component\Validator\Constraints\MaxCountValidator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class MaxCountValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new MaxCountValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    abstract protected function createCollection(array $content);

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new MaxCount(6));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsCountableType()
    {
        $this->validator->validate(new \stdClass(), new MaxCount(5));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new MaxCount(3);
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array($this->createCollection(array(1))),
            array($this->createCollection(array(1, 2))),
            array($this->createCollection(array(1, 2, 3))),
            array($this->createCollection(array('a' => 1, 'b' => 2, 'c' => 3))),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new MaxCount(array(
            'limit' => 3,
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ count }}' => count($value),
                '{{ limit }}' => 3,
            )), $value, 3);

        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array($this->createCollection(array(1, 2, 3, 4))),
            array($this->createCollection(array(1, 2, 3, 4, 5))),
            array($this->createCollection(array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4))),
        );
    }

    public function testDefaultOption()
    {
        $constraint = new MaxCount(5);

        $this->assertEquals(5, $constraint->limit);
    }
}
