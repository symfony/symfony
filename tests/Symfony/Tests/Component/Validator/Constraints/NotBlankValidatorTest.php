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

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotBlankValidator;

class NotBlankValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new NotBlankValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($date)
    {
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
        $this->assertFalse($this->validator->isValid(null, new NotBlank()));
    }

    public function testBlankIsInvalid()
    {
        $this->assertFalse($this->validator->isValid('', new NotBlank()));
    }

    public function testFalseIsInvalid()
    {
        $this->assertFalse($this->validator->isValid(false, new NotBlank()));
    }

    public function testEmptyArrayIsInvalid()
    {
        $this->assertFalse($this->validator->isValid(array(), new NotBlank()));
    }

    public function testSetMessage()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array());
    }
}

