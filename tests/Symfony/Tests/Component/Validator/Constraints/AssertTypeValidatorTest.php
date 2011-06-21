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

use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\TypeValidator;
use Symfony\Component\Validator\ConstraintViolation;

class TypeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected static $file;

    protected $validator;

    protected function setUp()
    {
        $this->validator = new TypeValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Type(array('type' => 'integer'))));
    }

    public function testEmptyIsValidIfString()
    {
        $this->assertTrue($this->validator->isValid('', new Type(array('type' => 'string'))));
    }

    public function testEmptyIsInvalidIfNoString()
    {
        $this->assertFalse($this->validator->isValid('', new Type(array('type' => 'integer'))));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $type)
    {
        $constraint = new Type(array('type' => $type));

        $this->assertTrue($this->validator->isValid($value, $constraint));
    }

    public function getValidValues()
    {
        $object = new \stdClass();
        $file = $this->createFile();

        return array(
            array(true, 'boolean'),
            array(false, 'boolean'),
            array(true, 'bool'),
            array(false, 'bool'),
            array(0, 'numeric'),
            array('0', 'numeric'),
            array(1.5, 'numeric'),
            array('1.5', 'numeric'),
            array(0, 'integer'),
            array(1.5, 'float'),
            array('12345', 'string'),
            array(array(), 'array'),
            array($object, 'object'),
            array($object, 'stdClass'),
            array($file, 'resource'),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $type)
    {
        $constraint = new Type(array('type' => $type));

        $this->assertFalse($this->validator->isValid($value, $constraint));
    }

    public function testConstraintViolationCanHandleArrayValue()
    {
        $constraint = new Type(array('type' => 'string'));
        $this->validator->isValid(array(0 => "Test"), $constraint);

        $violation = new ConstraintViolation(
            '{{ value }}',
            $this->validator->getMessageParameters(),
            '',
            '',
            ''
        );

        $this->assertEquals('Array', $violation->getMessage());
    }

    public function getInvalidValues()
    {
        $object = new \stdClass();
        $file = $this->createFile();

        return array(
            array('foobar', 'numeric'),
            array('foobar', 'boolean'),
            array('0', 'integer'),
            array('1.5', 'float'),
            array(12345, 'string'),
            array($object, 'boolean'),
            array($object, 'numeric'),
            array($object, 'integer'),
            array($object, 'float'),
            array($object, 'string'),
            array($object, 'resource'),
            array($file, 'boolean'),
            array($file, 'numeric'),
            array($file, 'integer'),
            array($file, 'float'),
            array($file, 'string'),
            array($file, 'object'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Type(array(
            'type' => 'numeric',
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
            '{{ type }}' => 'numeric',
        ));
    }

    protected function createFile()
    {
        if (!self::$file) {
            self::$file = fopen(__FILE__, 'r');
        }

        return self::$file;
    }

    public static function tearDownAfterClass()
    {
        if (self::$file) {
            fclose(self::$file);
        }
    }
}
