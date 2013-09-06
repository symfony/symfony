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

use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\CountValidator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class CountValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new CountValidator();
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

        $this->validator->validate(null, new Count(6));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsCountableType()
    {
        $this->validator->validate(new \stdClass(), new Count(5));
    }

    public function getThreeOrLessElements()
    {
        return array(
            array($this->createCollection(array(1))),
            array($this->createCollection(array(1, 2))),
            array($this->createCollection(array(1, 2, 3))),
            array($this->createCollection(array('a' => 1, 'b' => 2, 'c' => 3))),
        );
    }

    public function getFourElements()
    {
        return array(
            array($this->createCollection(array(1, 2, 3, 4))),
            array($this->createCollection(array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4))),
        );
    }

    public function getNotFourElements()
    {
        return array_merge(
            $this->getThreeOrLessElements(),
            $this->getFiveOrMoreElements()
        );
    }

    public function getFiveOrMoreElements()
    {
        return array(
            array($this->createCollection(array(1, 2, 3, 4, 5))),
            array($this->createCollection(array(1, 2, 3, 4, 5, 6))),
            array($this->createCollection(array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5))),
        );
    }

    /**
     * @dataProvider getThreeOrLessElements
     */
    public function testValidValuesMax($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Count(array('max' => 3));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getFiveOrMoreElements
     */
    public function testValidValuesMin($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Count(array('min' => 5));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getFourElements
     */
    public function testValidValuesExact($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Count(4);
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getFiveOrMoreElements
     */
    public function testInvalidValuesMax($value)
    {
        $constraint = new Count(array(
            'max' => 4,
            'maxMessage' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ count }}' => count($value),
                '{{ limit }}' => 4,
            )), $value, 4);

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getThreeOrLessElements
     */
    public function testInvalidValuesMin($value)
    {
        $constraint = new Count(array(
            'min' => 4,
            'minMessage' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ count }}' => count($value),
                '{{ limit }}' => 4,
            )), $value, 4);

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getNotFourElements
     */
    public function testInvalidValuesExact($value)
    {
        $constraint = new Count(array(
            'min' => 4,
            'max' => 4,
            'exactMessage' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
            '{{ count }}' => count($value),
            '{{ limit }}' => 4,
        )), $value, 4);

        $this->validator->validate($value, $constraint);
    }

    public function testDefaultOption()
    {
        $constraint = new Count(5);

        $this->assertEquals(5, $constraint->min);
        $this->assertEquals(5, $constraint->max);
    }
}
