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

use Symfony\Component\Validator\Constraints\Size;
use Symfony\Component\Validator\Constraints\SizeValidator;

class SizeAmbiguous implements \Countable
{
    public function __toString()
    {
        return '';
    }

    public function count()
    {
        return 0;
    }
}

class SizeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new SizeValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate(null, new Size(array('min' => 10, 'max' => 20)));
    }

    public function testNullIsValidAsAString()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate(null, new Size(array('type' => 'string', 'min' => 10, 'max' => 20)));
    }

    public function testNullIsValidAsACollectionCollection()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate(null, new Size(array('type' => 'collection', 'min' => 10, 'max' => 20)));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate('', new Size(array('min' => 10, 'max' => 20)));
    }

    public function testEmptyStringIsValidAsAString()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate('', new Size(array('type' => 'string', 'min' => 10, 'max' => 20)));
    }

    /**
     * @dataProvider getValidStringValues
     */
    public function testValidStringValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            return $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Size(array('min' => 6, 'max' => 10));
        $this->validator->validate($value, $constraint);
    }

    public function getValidStringValues()
    {
        return array(
            array(123456),
            array(1234567890),
            array('123456'),
            array('1234567890'),
            array('üüüüüü', true),
            array('üüüüüüüüüü', true),
            array('éééééé', true),
            array('éééééééééé', true),
        );
    }

    /**
     * @dataProvider getInvalidStringValues
     */
    public function testInvalidStringValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            return $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->once())->method('addViolation');

        $this->validator->validate($value, new Size(array('min' => 6, 'max' => 10)));
    }

    public function getInvalidStringValues()
    {
        return array(
            array(12345),
            array(12345678901),
            array('12345'),
            array('12345678901'),
            array('üüüüü', true),
            array('üüüüüüüüüüü', true),
            array('ééééé', true),
            array('ééééééééééé', true),
        );
    }

    /**
     * @dataProvider getValidCollectionValues
     */
    public function testValidCollectionValue($value)
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate($value, new Size(array('min' => 10, 'max' => 20)));
    }

    public function getValidCollectionValues()
    {
        $countable = $this->getMock('Countable');
        $countable->expects($this->any())->method('count')->will($this->returnValue(15));

        return array(
            array($countable),
            array(range(1, 15)),
        );
    }

    /**
     * @dataProvider getInvalidCollectionValues
     */
    public function testInvalidCollectionValue($value)
    {
        $this->context->expects($this->once())->method('addViolation');

        $this->validator->validate($value, new Size(array('min' => 10, 'max' => 20)));
    }

    public function getInvalidCollectionValues()
    {
        $tooSmallCountable = $this->getMock('Countable');
        $tooSmallCountable->expects($this->any())->method('count')->will($this->returnValue(5));

        $tooBigCountable = $this->getmock('countable');
        $tooBigCountable->expects($this->any())->method('count')->will($this->returnValue(25));

        return array(
            array($tooSmallCountable),
            array($tooBigCountable),
            array(array()),
            array(range(1, 5)),
            array(range(1, 25)),
        );
    }

    /**
     * @expectedException RuntimeException
     */
    public function throwsAnExceptionWhenOnAmbiguousValue()
    {
        $this->validator->validate(new SizeAmbiguous(), new Size(array('min' => 10, 'max' => 20)));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsEitherStringOrCollectionCompatible()
    {
        $this->validator->validate(new \stdCLass(), new Size(array('min' => 10, 'max' => 20)));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $countable = $this->getMock('Countable');

        $this->validator->validate($countable, new Size(array('type' => 'string', 'min' => 6, 'max' => 10)));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsCollectionCompatibleType()
    {
        $this->validator->validate('string', new Size(array('type' => 'collection', 'min' => 6, 'max' => 10)));
    }
}
