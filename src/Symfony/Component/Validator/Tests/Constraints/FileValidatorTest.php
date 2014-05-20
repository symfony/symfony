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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\FileValidator;

abstract class FileValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;
    protected $path;
    protected $file;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new FileValidator();
        $this->validator->initialize($this->context);
        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'FileValidatorTest';
        $this->file = fopen($this->path, 'w');
    }

    protected function tearDown()
    {
        if (is_resource($this->file)) {
            fclose($this->file);
        }

        if (file_exists($this->path)) {
            unlink($this->path);
        }

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
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
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

        $file = new UploadedFile($this->path, 'originalName', null, null, null, true);
        $this->validator->validate($file, new File());
    }

    public function provideMaxSizeExceededTests()
    {
        return array(
            array(11, 10, '11', '10', 'bytes'),

            array(ceil(1.005*1000), ceil(1.005*1000) - 1, '1005', '1004', 'bytes'),
            array(ceil(1.005*1000*1000), ceil(1.005*1000*1000) - 1, '1005000', '1004999', 'bytes'),

            // round(size) == 1.01kB, limit == 1kB
            array(ceil(1.005*1000), 1000, '1.01', '1', 'kB'),
            array(ceil(1.005*1000), '1k', '1.01', '1', 'kB'),

            // round(size) == 1kB, limit == 1kB -> use bytes
            array(ceil(1.004*1000), 1000, '1004', '1000', 'bytes'),
            array(ceil(1.004*1000), '1k', '1004', '1000', 'bytes'),

            array(1000 + 1, 1000, '1001', '1000', 'bytes'),
            array(1000 + 1, '1k', '1001', '1000', 'bytes'),

            // round(size) == 1.01MB, limit == 1MB
            array(ceil(1.005*1000*1000), 1000*1000, '1.01', '1', 'MB'),
            array(ceil(1.005*1000*1000), '1000k', '1.01', '1', 'MB'),
            array(ceil(1.005*1000*1000), '1M', '1.01', '1', 'MB'),

            // round(size) == 1MB, limit == 1MB -> use kB
            array(ceil(1.004*1000*1000), 1000*1000, '1004', '1000', 'kB'),
            array(ceil(1.004*1000*1000), '1000k', '1004', '1000', 'kB'),
            array(ceil(1.004*1000*1000), '1M', '1004', '1000', 'kB'),

            array(1000*1000 + 1, 1000*1000, '1000001', '1000000', 'bytes'),
            array(1000*1000 + 1, '1000k', '1000001', '1000000', 'bytes'),
            array(1000*1000 + 1, '1M', '1000001', '1000000', 'bytes'),
        );
    }

    /**
     * @dataProvider provideMaxSizeExceededTests
     */
    public function testMaxSizeExceeded($bytesWritten, $limit, $sizeAsString, $limitAsString, $suffix)
    {
        fseek($this->file, $bytesWritten-1, SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File(array(
            'maxSize'           => $limit,
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ limit }}'   => $limitAsString,
                '{{ size }}'    => $sizeAsString,
                '{{ suffix }}'  => $suffix,
                '{{ file }}'    => $this->path,
            ));

        $this->validator->validate($this->getFile($this->path), $constraint);
    }

    public function provideMaxSizeNotExceededTests()
    {
        return array(
            array(10, 10),
            array(9, 10),

            array(1000, '1k'),
            array(1000 - 1, '1k'),

            array(1000*1000, '1M'),
            array(1000*1000 - 1, '1M'),
        );
    }

    /**
     * @dataProvider provideMaxSizeNotExceededTests
     */
    public function testMaxSizeNotExceeded($bytesWritten, $limit)
    {
        fseek($this->file, $bytesWritten-1, SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File(array(
            'maxSize'           => $limit,
            'maxSizeMessage'    => 'myMessage',
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($this->getFile($this->path), $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
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
    public function testUploadedFileError($error, $message, array $params = array(), $maxSize = null)
    {
        $file = new UploadedFile('/path/to/file', 'originalName', 'mime', 0, $error);

        $constraint = new File(array(
            $message => 'myMessage',
            'maxSize' => $maxSize
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
            // when no maxSize is specified on constraint, it should use the ini value
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => UploadedFile::getMaxFilesize(),
                '{{ suffix }}' => 'bytes',
            ));

            // it should use the smaller limitation (maxSize option in this case)
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => 1,
                '{{ suffix }}' => 'bytes',
            ), '1');

            // it correctly parses the maxSize option and not only uses simple string comparison
            // 1000M should be bigger than the ini value
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => UploadedFile::getMaxFilesize(),
                '{{ suffix }}' => 'bytes',
            ), '1000M');
        }

        return $tests;
    }

    abstract protected function getFile($filename);
}
