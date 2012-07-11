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

use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\FileValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class FileValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;
    protected $path;
    protected $file;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\File\UploadedFile')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new FileValidator();
        $this->validator->initialize($this->context);
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'FileValidatorTest';
        $this->file = fopen($this->path, 'w');
    }

    protected function tearDown()
    {
        fclose($this->file);

        $this->context = null;
        $this->validator = null;
        $this->path = null;
        $this->file = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new File());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new File());
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleTypeOrFile()
    {
        $this->validator->validate(new \stdClass(), new File());
    }

    public function testValidFile()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($this->path, new File());
    }

    public function testValidUploadedfile()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $file = new UploadedFile($this->path, 'originalName');
        $this->validator->validate($file, new File());
    }

    public function testTooLargeBytes()
    {
        fwrite($this->file, str_repeat('0', 11));

        $constraint = new File(array(
            'maxSize'           => 10,
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ limit }}'   => '10',
                '{{ size }}'    => '11',
                '{{ suffix }}'  => 'bytes',
                '{{ file }}'    => $this->path,
            ));

        $this->validator->validate($this->getFile($this->path), $constraint);
    }

    public function testTooLargeKiloBytes()
    {
        fwrite($this->file, str_repeat('0', 1400));

        $constraint = new File(array(
            'maxSize'           => '1k',
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ limit }}'   => '1',
                '{{ size }}'    => '1.4',
                '{{ suffix }}'  => 'kB',
                '{{ file }}'    => $this->path,
            ));

        $this->validator->validate($this->getFile($this->path), $constraint);
    }

    public function testTooLargeMegaBytes()
    {
        fwrite($this->file, str_repeat('0', 1400000));

        $constraint = new File(array(
            'maxSize'           => '1M',
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ limit }}'   => '1',
                '{{ size }}'    => '1.4',
                '{{ suffix }}'  => 'MB',
                '{{ file }}'    => $this->path,
            ));

        $this->validator->validate($this->getFile($this->path), $constraint);
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxSize()
    {
        $constraint = new File(array(
            'maxSize' => '1abc',
        ));

        $this->validator->validate($this->path, $constraint);
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

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
        ));

        $this->validator->validate($file, $constraint);
    }

    public function testValidWildcardMimeType()
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

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new File(array(
            'mimeTypes' => array('image/*'),
        ));

        $this->validator->validate($file, $constraint);
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
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('application/pdf'))
        ;

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ type }}'    => '"application/pdf"',
                '{{ types }}'   => '"image/png", "image/jpg"',
                '{{ file }}'    => $this->path,
            ));

        $this->validator->validate($file, $constraint);
    }

    public function testInvalidWildcardMimeType()
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
            ->will($this->returnValue('application/pdf'))
        ;

        $constraint = new File(array(
            'mimeTypes' => array('image/*', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ type }}'    => '"application/pdf"',
                '{{ types }}'   => '"image/*", "image/jpg"',
                '{{ file }}'    => $this->path,
            ));

        $this->validator->validate($file, $constraint);
    }

    /**
     * @dataProvider uploadedFileErrorProvider
     */
    public function testUploadedFileError($error, $message, array $params = array())
    {
        $file = new UploadedFile('/path/to/file', 'originalName', 'mime', 0, $error);

        $constraint = new File(array(
            $message => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $params);

        $this->validator->validate($file, $constraint);

    }

    public function uploadedFileErrorProvider()
    {
        $tests = array(
            array(UPLOAD_ERR_FORM_SIZE, 'uploadFormSizeErrorMessage'),
            array(UPLOAD_ERR_PARTIAL, 'uploadPartialErrorMessage'),
            array(UPLOAD_ERR_NO_FILE, 'uploadNoFileErrorMessage'),
            array(UPLOAD_ERR_NO_TMP_DIR, 'uploadNoTmpDirErrorMessage'),
            array(UPLOAD_ERR_CANT_WRITE, 'uploadCantWriteErrorMessage'),
            array(UPLOAD_ERR_EXTENSION, 'uploadExtensionErrorMessage'),
        );

        if (class_exists('Symfony\Component\HttpFoundation\File\UploadedFile')) {
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => UploadedFile::getMaxFilesize(),
                '{{ suffix }}' => 'bytes',
            ));
        }

        return $tests;
    }

    abstract protected function getFile($filename);
}
