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

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LengthValidator;

class LengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new LengthValidator();
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

        $this->validator->validate(null, new Length(6));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Length(6));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Length(5));
    }

    public function getThreeOrLessCharacters()
    {
        return array(
            array(12),
            array('12'),
            array('üü', true),
            array('éé', true),
            array(123),
            array('123'),
            array('üüü', true),
            array('ééé', true),
        );
    }

    public function getFourCharacters()
    {
        return array(
            array(1234),
            array('1234'),
            array('üüüü', true),
            array('éééé', true),
        );
    }

    public function getNotFourCharacters()
    {
        return array_merge(
            $this->getThreeOrLessCharacters(),
            $this->getFiveOrMoreCharacters()
        );
    }

    public function getFiveOrMoreCharacters()
    {
        return array(
            array(12345),
            array('12345'),
            array('üüüüü', true),
            array('ééééé', true),
            array(123456),
            array('123456'),
            array('üüüüüü', true),
            array('éééééé', true),
        );
    }

    /**
     * @dataProvider getFiveOrMoreCharacters
     */
    public function testValidValuesMin($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Length(array('min' => 5));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getThreeOrLessCharacters
     */
    public function testValidValuesMax($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Length(array('max' => 3));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getFourCharacters
     */
    public function testValidValuesExact($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Length(4);
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getThreeOrLessCharacters
     */
    public function testInvalidValuesMin($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $constraint = new Length(array(
            'min' => 4,
            'minMessage' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ value }}' => (string) $value,
                '{{ limit }}' => 4,
            )), $this->identicalTo($value), 4);

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getFiveOrMoreCharacters
     */
    public function testInvalidValuesMax($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $constraint = new Length(array(
            'max' => 4,
            'maxMessage' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ value }}' => (string) $value,
                '{{ limit }}' => 4,
            )), $this->identicalTo($value), 4);

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getNotFourCharacters
     */
    public function testInvalidValuesExact($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $constraint = new Length(array(
            'min' => 4,
            'max' => 4,
            'exactMessage' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ value }}' => (string) $value,
                '{{ limit }}' => 4,
            )), $this->identicalTo($value), 4);

        $this->validator->validate($value, $constraint);
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new Length(5);

        $this->assertEquals(5, $constraint->min);
        $this->assertEquals(5, $constraint->max);
    }
}
