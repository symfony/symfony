<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\MaxLength;
use Symfony\Component\Validator\Constraints\MaxLengthValidator;

class MaxLengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new MaxLengthValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new MaxLength(array('limit' => 5))));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new MaxLength(array('limit' => 5))));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new MaxLength(array('limit' => 5)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $skip = false)
    {
        if (!$skip) {
            $constraint = new MaxLength(array('limit' => 5));
            $this->assertTrue($this->validator->isValid($value, $constraint));
        }
    }

    public function getValidValues()
    {
        return array(
            array(12345),
            array('12345'),
            array('üüüüü', !function_exists('mb_strlen')),
            array('ééééé', !function_exists('mb_strlen')),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $skip = false)
    {
        if (!$skip) {
            $constraint = new MaxLength(array('limit' => 5));
            $this->assertFalse($this->validator->isValid($value, $constraint));
        }
    }

    public function getInvalidValues()
    {
        return array(
            array(123456),
            array('123456'),
            array('üüüüüü', !function_exists('mb_strlen')),
            array('éééééé', !function_exists('mb_strlen')),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new MaxLength(array(
            'limit' => 5,
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('123456', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => '123456',
            '{{ limit }}' => 5,
        ));
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new MaxLength(array(
            'limit' => 5,
        ));

        $this->assertEquals('limit', $constraint->getDefaultOption());
    }
}
