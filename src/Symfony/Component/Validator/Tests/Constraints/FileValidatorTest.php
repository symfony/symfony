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
use Symfony\Component\Validator\Validation;

abstract class FileValidatorTest extends AbstractConstraintValidatorTest
{
    protected $path;

    protected $file;

    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new FileValidator();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'FileValidatorTest';
        $this->file = fopen($this->path, 'w');
    }

    protected function tearDown()
    {
        parent::tearDown();

        if (is_resource($this->file)) {
            fclose($this->file);
        }

        if (file_exists($this->path)) {
            unlink($this->path);
        }

        $this->path = null;
        $this->file = null;
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new File());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new File());

        $this->assertNoViolation();
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
        $this->validator->validate($this->path, new File());

        $this->assertNoViolation();
    }

    public function testValidUploadedfile()
    {
        $file = new UploadedFile($this->path, 'originalName', null, null, null, true);
        $this->validator->validate($file, new File());

        $this->assertNoViolation();
    }

    public function provideMaxSizeExceededTests()
    {
        // We have various interesting limit - size combinations to test.
        // Assume a limit of 1000 bytes (1 kB). Then the following table
        // lists the violation messages for different file sizes:

        // -----------+--------------------------------------------------------
        // Size       | Violation Message
        // -----------+--------------------------------------------------------
        // 1000 bytes | No violation
        // 1001 bytes | "Size of 1001 bytes exceeded limit of 1000 bytes"
        // 1004 bytes | "Size of 1004 bytes exceeded limit of 1000 bytes"
        //            | NOT: "Size of 1 kB exceeded limit of 1 kB"
        // 1005 bytes | "Size of 1.01 kB exceeded limit of 1 kB"
        // -----------+--------------------------------------------------------

        // As you see, we have two interesting borders:

        // 1000/1001 - The border as of which a violation occurs
        // 1004/1005 - The border as of which the message can be rounded to kB

        // Analogous for kB/MB.

        // Prior to Symfony 2.5, violation messages are always displayed in the
        // same unit used to specify the limit.

        // As of Symfony 2.5, the above logic is implemented.
        return array(
            // limit in bytes
            array(1001, 1000, '1001', '1000', 'bytes'),
            array(1004, 1000, '1004', '1000', 'bytes'),
            array(1005, 1000, '1005', '1000', 'bytes'),

            array(1000001, 1000000, '1000001', '1000000', 'bytes'),
            array(1004999, 1000000, '1004999', '1000000', 'bytes'),
            array(1005000, 1000000, '1005000', '1000000', 'bytes'),

            // limit in kB
            //array(1001, '1k') OK in 2.4, not in 2.5
            //array(1004, '1k') OK in 2.4, not in 2.5
            array(1005, '1k', '1.01', '1', 'kB'),

            //array(1000001, '1000k') OK in 2.4, not in 2.5
            array(1004999, '1000k', '1005', '1000', 'kB'),
            array(1005000, '1000k', '1005', '1000', 'kB'),

            // limit in MB
            //array(1000001, '1M') OK in 2.4, not in 2.5
            //array(1004999, '1M') OK in 2.4, not in 2.5
            array(1005000, '1M', '1.01', '1', 'MB'),
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

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', $limitAsString)
            ->setParameter('{{ size }}', $sizeAsString)
            ->setParameter('{{ suffix }}', $suffix)
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->assertRaised();
    }

    public function provideMaxSizeNotExceededTests()
    {
        return array(
            // limit in bytes
            array(1000, 1000),
            array(1000000, 1000000),

            // limit in kB
            array(1000, '1k'),
            array(1000000, '1000k'),

            // as of Symfony 2.5, the following are not accepted anymore
            array(1001, '1k'),
            array(1004, '1k'),
            array(1000001, '1000k'),

            // limit in MB
            array(1000000, '1M'),

            // as of Symfony 2.5, the following are not accepted anymore
            array(1000001, '1M'),
            array(1004999, '1M'),
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

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->assertNoViolation();
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
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('image/jpg'));

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
        ));

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    public function testValidWildcardMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('image/jpg'));

        $constraint = new File(array(
            'mimeTypes' => array('image/*'),
        ));

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('application/pdf'));

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/png", "image/jpg"')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->assertRaised();
    }

    public function testInvalidWildcardMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('application/pdf'));

        $constraint = new File(array(
            'mimeTypes' => array('image/*', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/*", "image/jpg"')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->assertRaised();
    }

    /**
     * @dataProvider uploadedFileErrorProvider
     */
    public function testUploadedFileError($error, $message, array $params = array(), $maxSize = null)
    {
        $file = new UploadedFile('/path/to/file', 'originalName', 'mime', 0, $error);

        $constraint = new File(array(
            $message => 'myMessage',
            'maxSize' => $maxSize,
        ));

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameters($params)
            ->assertRaised();
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
