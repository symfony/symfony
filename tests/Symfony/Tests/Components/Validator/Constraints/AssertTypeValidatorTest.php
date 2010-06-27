<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\AssertType;
use Symfony\Components\Validator\Constraints\AssertTypeValidator;

class AssertTypeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected static $file;

    protected $validator;

    public function setUp()
    {
        $this->validator = new AssertTypeValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new AssertType(array('type' => 'integer'))));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $type)
    {
        $constraint = new AssertType(array('type' => $type));

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
        $constraint = new AssertType(array('type' => $type));

        $this->assertFalse($this->validator->isValid($value, $constraint));
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
        $constraint = new AssertType(array(
            'type' => 'numeric',
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'value' => 'foobar',
            'type' => 'numeric',
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