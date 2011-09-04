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

use Symfony\Component\Validator\Constraints\Max;
use Symfony\Component\Validator\Constraints\MaxValidator;

class MaxValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new MaxValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Max(array('limit' => 10))));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $constraint = new Max(array('limit' => 10));
        $this->assertTrue($this->validator->isValid($value, $constraint));
    }

    public function getValidValues()
    {
        return array(
            array(9.999999),
            array(10),
            array(10.0),
            array('10'),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Max(array('limit' => 10));
        $this->assertFalse($this->validator->isValid($value, $constraint));
    }

    public function getInvalidValues()
    {
        return array(
            array(10.00001),
            array('10.00001'),
            array(new \stdClass()),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Max(array(
            'limit' => 10,
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(11, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 11,
            '{{ limit }}' => 10,
        ));
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new Max(array(
            'limit' => 10,
        ));

        $this->assertEquals('limit', $constraint->getDefaultOption());
    }
}
