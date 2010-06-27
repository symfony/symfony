<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\File;
use Symfony\Components\Validator\Constraints\FileValidator;

class FileValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $path;
    protected $file;

    public function setUp()
    {
        $this->validator = new FileValidator();
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'FileValidatorTest';
        $this->file = fopen($this->path, 'w');
    }

    public function tearDown()
    {
        fclose($this->file);
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new File()));
    }

    public function testExpectsStringCompatibleTypeOrFile()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new File());
    }

    public function testValidFile()
    {
        $this->assertTrue($this->validator->isValid($this->path, new File()));
    }

    public function testTooLargeBytes()
    {
        fwrite($this->file, str_repeat('0', 11));

        $constraint = new File(array(
            'maxSize' => 10,
            'maxSizeMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->path, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'limit' => '10 bytes',
            'size' => '11 bytes',
            'file' => $this->path,
        ));
    }

    public function testTooLargeKiloBytes()
    {
        fwrite($this->file, str_repeat('0', 1400));

        $constraint = new File(array(
            'maxSize' => '1k',
            'maxSizeMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->path, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'limit' => '1 kB',
            'size' => '1.4 kB',
            'file' => $this->path,
        ));
    }

    public function testTooLargeMegaBytes()
    {
        fwrite($this->file, str_repeat('0', 1400000));

        $constraint = new File(array(
            'maxSize' => '1M',
            'maxSizeMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($this->path, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'limit' => '1 MB',
            'size' => '1.4 MB',
            'file' => $this->path,
        ));
    }

    public function testInvalidMaxSize()
    {
        $constraint = new File(array(
            'maxSize' => '1abc',
        ));

        $this->setExpectedException('Symfony\Components\Validator\Exception\ConstraintDefinitionException');

        $this->validator->isValid($this->path, $constraint);
    }

    public function testFileNotFound()
    {
        $constraint = new File(array(
            'notFoundMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'file' => 'foobar',
        ));
    }

    public function testValidMimeType()
    {
        $file = $this->getMock('Symfony\Components\File\File', array(), array(), '', false);
        $file->expects($this->any())
                 ->method('getPath')
                 ->will($this->returnValue($this->path));
        $file->expects($this->any())
                 ->method('getMimeType')
                 ->will($this->returnValue('image/jpg'));

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
        ));

        $this->assertTrue($this->validator->isValid($file, $constraint));
    }

    public function testInvalidMimeType()
    {
        $file = $this->getMock('Symfony\Components\File\File', array(), array(), '', false);
        $file->expects($this->any())
                 ->method('getPath')
                 ->will($this->returnValue($this->path));
        $file->expects($this->any())
                 ->method('getMimeType')
                 ->will($this->returnValue('application/pdf'));

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($file, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'type' => '"application/pdf"',
            'types' => '"image/png", "image/jpg"',
            'file' => $this->path,
        ));
    }
}