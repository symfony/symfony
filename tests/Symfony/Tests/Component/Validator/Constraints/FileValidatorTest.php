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

use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\FileValidator;
use Symfony\Component\HttpFoundation\File\File as FileObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $path;
    protected $file;

    protected function setUp()
    {
        $this->validator = new FileValidator();
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'FileValidatorTest';
        $this->file = fopen($this->path, 'w');
    }

    protected function tearDown()
    {
        fclose($this->file);

        $this->validator = null;
        $this->path = null;
        $this->file = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new File()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new File()));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleTypeOrFile()
    {
        $this->validator->isValid(new \stdClass(), new File());
    }

    public function testValidFile()
    {
        $this->assertTrue($this->validator->isValid($this->path, new File()));
    }

    public function testValidUploadedfile()
    {
        $file = new UploadedFile($this->path, 'originalName');
        $this->assertTrue($this->validator->isValid($file, new File()));
    }

    public function testTooLargeBytes()
    {
        fwrite($this->file, str_repeat('0', 11));

        $constraint = new File(array(
            'maxSize'           => 10,
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->assertFileValid($this->path, $constraint, false);
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ limit }}'   => '10 bytes',
            '{{ size }}'    => '11 bytes',
            '{{ file }}'    => $this->path,
        ));
    }

    public function testTooLargeKiloBytes()
    {
        fwrite($this->file, str_repeat('0', 1400));

        $constraint = new File(array(
            'maxSize'           => '1k',
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->assertFileValid($this->path, $constraint, false);
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ limit }}'   => '1 kB',
            '{{ size }}'    => '1.4 kB',
            '{{ file }}'    => $this->path,
        ));
    }

    public function testTooLargeMegaBytes()
    {
        fwrite($this->file, str_repeat('0', 1400000));

        $constraint = new File(array(
            'maxSize'           => '1M',
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->assertFileValid($this->path, $constraint, false);
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ limit }}'   => '1 MB',
            '{{ size }}'    => '1.4 MB',
            '{{ file }}'    => $this->path,
        ));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxSize()
    {
        $constraint = new File(array(
            'maxSize' => '1abc',
        ));

        $this->validator->isValid($this->path, $constraint);
    }

    public function testFileNotFound()
    {
        $constraint = new File(array(
            'notFoundMessage' => 'myMessage',
        ));

        $this->assertFileValid('foobar', $constraint, false);
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ file }}' => 'foobar',
        ));
    }

    public function testValidMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path))
        ;
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('image/jpg'))
        ;

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
        ));

        $this->assertTrue($this->validator->isValid($file, $constraint));
    }

    public function testInvalidMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path))
        ;
        $file
            ->expects($this->exactly(2))
            ->method('getMimeType')
            ->will($this->returnValue('application/pdf'))
        ;

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid($file, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ type }}'    => '"application/pdf"',
            '{{ types }}'   => '"image/png", "image/jpg"',
            '{{ file }}'    => $this->path,
        ));
    }

    /**
     * @dataProvider uploadedFileErrorProvider
     */
    public function testUploadedFileError($error, $message)
    {
        $file = new UploadedFile('/path/to/file', 'originalName', 'mime', 0, $error);

        $options[$message] = 'myMessage';

        $constraint = new File($options);

        $this->assertFalse($this->validator->isValid($file, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');

    }

    public function uploadedFileErrorProvider()
    {
        return array(
            array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage'),
            array(UPLOAD_ERR_FORM_SIZE, 'uploadFormSizeErrorMessage'),
            array(UPLOAD_ERR_PARTIAL, 'uploadErrorMessage'),
            array(UPLOAD_ERR_NO_TMP_DIR, 'uploadErrorMessage'),
            array(UPLOAD_ERR_EXTENSION, 'uploadErrorMessage'),
        );
    }

    protected function assertFileValid($filename, File $constraint, $valid = true)
    {
        $this->assertEquals($this->validator->isValid($filename, $constraint), $valid);
        if (file_exists($filename)) {
            $this->assertEquals($this->validator->isValid(new FileObject($filename), $constraint), $valid);
        }
    }
}
