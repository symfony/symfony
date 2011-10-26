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

use Symfony\Component\Validator\Constraints\Size;
use Symfony\Component\Validator\Constraints\SizeValidator;

class SizeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new SizeValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Size(array('min' => 10, 'max' => 20))));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $constraint = new Size(array('min' => 10, 'max' => 20));
        $this->assertTrue($this->validator->isValid($value, $constraint));
    }

    public function getValidValues()
    {
        return array(
            array(10.00001),
            array(19.99999),
            array('10.00001'),
            array('19.99999'),
            array(10),
            array(20),
            array(10.0),
            array(20.0),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Size(array('min' => 10, 'max' => 20));
        $this->assertFalse($this->validator->isValid($value, $constraint));
    }

    public function getInvalidValues()
    {
        return array(
            array(9.999999),
            array(20.000001),
            array('9.999999'),
            array('20.000001'),
            array(new \stdClass()),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Size(array(
            'min' => 10,
            'max' => 20,
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->assertFalse($this->validator->isValid(9, $constraint));
        $this->assertEquals('myMinMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ value }}' => 9,
            '{{ limit }}' => 10,
        ), $this->validator->getMessageParameters());

        $this->assertFalse($this->validator->isValid(21, $constraint));
        $this->assertEquals('myMaxMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ value }}' => 21,
            '{{ limit }}' => 20,
        ), $this->validator->getMessageParameters());
    }
}
