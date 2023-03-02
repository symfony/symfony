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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

abstract class FileValidatorTestCase extends ConstraintValidatorTestCase
{
    protected $path;

    protected $file;

    protected function createValidator(): FileValidator
    {
        return new FileValidator();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'FileValidatorTest';
        $this->file = fopen($this->path, 'w');
        fwrite($this->file, ' ', 1);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (\is_resource($this->file)) {
            fclose($this->file);
        }

        if (file_exists($this->path)) {
            @unlink($this->path);
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

    public function testExpectsStringCompatibleTypeOrFile()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new File());
    }

    public function testValidFile()
    {
        $this->validator->validate($this->path, new File());

        $this->assertNoViolation();
    }

    public function testValidUploadedfile()
    {
        file_put_contents($this->path, '1');
        $file = new UploadedFile($this->path, 'originalName', null, null, true);
        $this->validator->validate($file, new File());

        $this->assertNoViolation();
    }

    public static function provideMaxSizeExceededTests()
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
        return [
            // limit in bytes
            [1001, 1000, '1001', '1000', 'bytes'],
            [1004, 1000, '1004', '1000', 'bytes'],
            [1005, 1000, '1.01', '1', 'kB'],

            [1000001, 1000000, '1000001', '1000000', 'bytes'],
            [1004999, 1000000, '1005', '1000', 'kB'],
            [1005000, 1000000, '1.01', '1', 'MB'],

            // limit in kB
            [1001, '1k', '1001', '1000', 'bytes'],
            [1004, '1k', '1004', '1000', 'bytes'],
            [1005, '1k', '1.01', '1', 'kB'],

            [1000001, '1000k', '1000001', '1000000', 'bytes'],
            [1004999, '1000k', '1005', '1000', 'kB'],
            [1005000, '1000k', '1.01', '1', 'MB'],

            // limit in MB
            [1000001, '1M', '1000001', '1000000', 'bytes'],
            [1004999, '1M', '1005', '1000', 'kB'],
            [1005000, '1M', '1.01', '1', 'MB'],

            // limit in KiB
            [1025, '1Ki', '1025', '1024', 'bytes'],
            [1029, '1Ki', '1029', '1024', 'bytes'],
            [1030, '1Ki', '1.01', '1', 'KiB'],

            [1048577, '1024Ki', '1048577', '1048576', 'bytes'],
            [1053818, '1024Ki', '1029.12', '1024', 'KiB'],
            [1053819, '1024Ki', '1.01', '1', 'MiB'],

            // limit in MiB
            [1048577, '1Mi', '1048577', '1048576', 'bytes'],
            [1053818, '1Mi', '1029.12', '1024', 'KiB'],
            [1053819, '1Mi', '1.01', '1', 'MiB'],

            // $limit < $coef, @see FileValidator::factorizeSizes()
            [169632, '100k', '169.63', '100', 'kB'],
            [1000001, '990k', '1000', '990', 'kB'],
            [123, '80', '123', '80', 'bytes'],
        ];
    }

    /**
     * @dataProvider provideMaxSizeExceededTests
     */
    public function testMaxSizeExceeded($bytesWritten, $limit, $sizeAsString, $limitAsString, $suffix)
    {
        fseek($this->file, $bytesWritten - 1, \SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File([
            'maxSize' => $limit,
            'maxSizeMessage' => 'myMessage',
        ]);

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', $limitAsString)
            ->setParameter('{{ size }}', $sizeAsString)
            ->setParameter('{{ suffix }}', $suffix)
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setParameter('{{ name }}', '"'.basename($this->path).'"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public static function provideMaxSizeNotExceededTests()
    {
        return [
            // 0 has no effect
            [100, 0],

            // limit in bytes
            [1000, 1000],
            [1000000, 1000000],

            // limit in kB
            [1000, '1k'],
            [1000000, '1000k'],

            // limit in MB
            [1000000, '1M'],

            // limit in KiB
            [1024, '1Ki'],
            [1048576, '1024Ki'],

            // limit in MiB
            [1048576, '1Mi'],
        ];
    }

    /**
     * @dataProvider provideMaxSizeNotExceededTests
     */
    public function testMaxSizeNotExceeded($bytesWritten, $limit)
    {
        fseek($this->file, $bytesWritten - 1, \SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File([
            'maxSize' => $limit,
            'maxSizeMessage' => 'myMessage',
        ]);

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidMaxSize()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $constraint = new File([
            'maxSize' => '1abc',
        ]);

        $this->validator->validate($this->path, $constraint);
    }

    public static function provideBinaryFormatTests()
    {
        return [
            [11, 10, null, '11', '10', 'bytes'],
            [11, 10, true, '11', '10', 'bytes'],
            [11, 10, false, '11', '10', 'bytes'],

            // round(size) == 1.01kB, limit == 1kB
            [ceil(1000 * 1.01), 1000, null, '1.01', '1', 'kB'],
            [ceil(1000 * 1.01), '1k', null, '1.01', '1', 'kB'],
            [ceil(1024 * 1.01), '1Ki', null, '1.01', '1', 'KiB'],

            [ceil(1024 * 1.01), 1024, true, '1.01', '1', 'KiB'],
            [ceil(1024 * 1.01 * 1000), '1024k', true, '1010', '1000', 'KiB'],
            [ceil(1024 * 1.01), '1Ki', true, '1.01', '1', 'KiB'],

            [ceil(1000 * 1.01), 1000, false, '1.01', '1', 'kB'],
            [ceil(1000 * 1.01), '1k', false, '1.01', '1', 'kB'],
            [ceil(1024 * 1.01 * 10), '10Ki', false, '10.34', '10.24', 'kB'],
        ];
    }

    /**
     * @dataProvider provideBinaryFormatTests
     */
    public function testBinaryFormat($bytesWritten, $limit, $binaryFormat, $sizeAsString, $limitAsString, $suffix)
    {
        fseek($this->file, $bytesWritten - 1, \SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File([
            'maxSize' => $limit,
            'binaryFormat' => $binaryFormat,
            'maxSizeMessage' => 'myMessage',
        ]);

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', $limitAsString)
            ->setParameter('{{ size }}', $sizeAsString)
            ->setParameter('{{ suffix }}', $suffix)
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setParameter('{{ name }}', '"'.basename($this->path).'"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public function testBinaryFormatNamed()
    {
        fseek($this->file, 10, \SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File(maxSize: 10, binaryFormat: true, maxSizeMessage: 'myMessage');

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', '10')
            ->setParameter('{{ size }}', '11')
            ->setParameter('{{ suffix }}', 'bytes')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setParameter('{{ name }}', '"'.basename($this->path).'"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public function testValidMimeType()
    {
        $file = $this
            ->getMockBuilder(\Symfony\Component\HttpFoundation\File\File::class)
            ->setConstructorArgs([__DIR__.'/Fixtures/foo'])
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->willReturn($this->path);
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->willReturn('image/jpg');

        $constraint = new File([
            'mimeTypes' => ['image/png', 'image/jpg'],
        ]);

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    public function testValidWildcardMimeType()
    {
        $file = $this
            ->getMockBuilder(\Symfony\Component\HttpFoundation\File\File::class)
            ->setConstructorArgs([__DIR__.'/Fixtures/foo'])
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->willReturn($this->path);
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->willReturn('image/jpg');

        $constraint = new File([
            'mimeTypes' => ['image/*'],
        ]);

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideMimeTypeConstraints
     */
    public function testInvalidMimeType(File $constraint)
    {
        $file = $this
            ->getMockBuilder(\Symfony\Component\HttpFoundation\File\File::class)
            ->setConstructorArgs([__DIR__.'/Fixtures/foo'])
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->willReturn($this->path);
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->willReturn('application/pdf');

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/png", "image/jpg"')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setParameter('{{ name }}', '"'.basename($this->path).'"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public static function provideMimeTypeConstraints(): iterable
    {
        yield 'Doctrine style' => [new File([
            'mimeTypes' => ['image/png', 'image/jpg'],
            'mimeTypesMessage' => 'myMessage',
        ])];
        yield 'named arguments' => [
            new File(mimeTypes: ['image/png', 'image/jpg'], mimeTypesMessage: 'myMessage'),
        ];
    }

    public function testInvalidWildcardMimeType()
    {
        $file = $this
            ->getMockBuilder(\Symfony\Component\HttpFoundation\File\File::class)
            ->setConstructorArgs([__DIR__.'/Fixtures/foo'])
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->willReturn($this->path);
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->willReturn('application/pdf');

        $constraint = new File([
            'mimeTypes' => ['image/*', 'image/jpg'],
            'mimeTypesMessage' => 'myMessage',
        ]);

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/*", "image/jpg"')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setParameter('{{ name }}', '"'.basename($this->path).'"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider provideDisallowEmptyConstraints
     */
    public function testDisallowEmpty(File $constraint)
    {
        ftruncate($this->file, 0);

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setParameter('{{ name }}', '"'.basename($this->path).'"')
            ->setCode(File::EMPTY_ERROR)
            ->assertRaised();
    }

    public static function provideDisallowEmptyConstraints(): iterable
    {
        yield 'Doctrine style' => [new File([
            'disallowEmptyMessage' => 'myMessage',
        ])];
        yield 'named arguments' => [
            new File(disallowEmptyMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider uploadedFileErrorProvider
     */
    public function testUploadedFileError($error, $message, array $params = [], $maxSize = null)
    {
        $file = new UploadedFile(tempnam(sys_get_temp_dir(), 'file-validator-test-'), 'originalName', 'mime', $error);

        $constraint = new File([
            $message => 'myMessage',
            'maxSize' => $maxSize,
        ]);

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameters($params)
            ->setCode($error)
            ->assertRaised();
    }

    public static function uploadedFileErrorProvider()
    {
        $tests = [
            [(string) \UPLOAD_ERR_FORM_SIZE, 'uploadFormSizeErrorMessage'],
            [(string) \UPLOAD_ERR_PARTIAL, 'uploadPartialErrorMessage'],
            [(string) \UPLOAD_ERR_NO_FILE, 'uploadNoFileErrorMessage'],
            [(string) \UPLOAD_ERR_NO_TMP_DIR, 'uploadNoTmpDirErrorMessage'],
            [(string) \UPLOAD_ERR_CANT_WRITE, 'uploadCantWriteErrorMessage'],
            [(string) \UPLOAD_ERR_EXTENSION, 'uploadExtensionErrorMessage'],
        ];

        if (class_exists(UploadedFile::class)) {
            // when no maxSize is specified on constraint, it should use the ini value
            $tests[] = [(string) \UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => UploadedFile::getMaxFilesize() / 1048576,
                '{{ suffix }}' => 'MiB',
            ]];

            // it should use the smaller limitation (maxSize option in this case)
            $tests[] = [(string) \UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => 1,
                '{{ suffix }}' => 'bytes',
            ], '1'];

            // access FileValidator::factorizeSizes() private method to format max file size
            $reflection = new \ReflectionClass(new FileValidator());
            $method = $reflection->getMethod('factorizeSizes');
            [, $limit, $suffix] = $method->invokeArgs(new FileValidator(), [0, UploadedFile::getMaxFilesize(), false]);

            // it correctly parses the maxSize option and not only uses simple string comparison
            // 1000G should be bigger than the ini value
            $tests[] = [(string) \UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => $limit,
                '{{ suffix }}' => $suffix,
            ], '1000G'];

            $tests[] = [(string) \UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => '100',
                '{{ suffix }}' => 'kB',
            ], '100K'];
        }

        return $tests;
    }

    public function testNegativeMaxSize()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('"-1" is not a valid maximum size.');

        $file = new File();
        $file->maxSize = -1;
    }

    /**
     * @dataProvider providerValidExtension
     */
    public function testExtensionValid(string $name)
    {
        $path = __DIR__.'/Fixtures/'.$name;
        $file = new \Symfony\Component\HttpFoundation\File\File($path);

        try {
            $file->getMimeType();
        } catch (\LogicException $e) {
            $this->markTestSkipped('Guessing the mime type is not possible');
        }

        $constraint = new File(mimeTypes: [], extensions: ['gif', 'txt'], extensionsMessage: 'myMessage');

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    public static function providerValidExtension(): iterable
    {
        yield ['test.gif'];
        yield ['test.png.gif'];
        yield ['ccc.txt'];
        yield ['uppercased-extension.TXT'];
    }

    /**
     * @dataProvider provideInvalidExtension
     */
    public function testExtensionInvalid(string $name, string $extension)
    {
        $path = __DIR__.'/Fixtures/'.$name;
        $file = new \Symfony\Component\HttpFoundation\File\File($path);

        $constraint = new File(extensions: ['png', 'svg'], extensionsMessage: 'myMessage');

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameters([
                '{{ file }}' => '"'.$path.'"',
                '{{ extension }}' => '"'.$extension.'"',
                '{{ extensions }}' => '"png", "svg"',
                '{{ name }}' => '"'.$name.'"',
            ])
            ->setCode(File::INVALID_EXTENSION_ERROR)
            ->assertRaised();
    }

    public static function provideInvalidExtension(): iterable
    {
        yield ['test.gif', 'gif'];
        yield ['test.png.gif', 'gif'];
        yield ['bar', ''];
    }

    public function testExtensionAutodetectMimeTypesInvalid()
    {
        $path = __DIR__.'/Fixtures/invalid-content.gif';
        $file = new \Symfony\Component\HttpFoundation\File\File($path);

        try {
            $file->getMimeType();
        } catch (\LogicException $e) {
            $this->markTestSkipped('Guessing the mime type is not possible');
        }

        $constraint = new File(mimeTypesMessage: 'myMessage', extensions: ['gif']);

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameters([
                '{{ file }}' => '"'.$path.'"',
                '{{ name }}' => '"invalid-content.gif"',
                '{{ type }}' => '"text/plain"',
                '{{ types }}' => '"image/gif"',
            ])
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testExtensionTypesIncoherent()
    {
        $path = __DIR__.'/Fixtures/invalid-content.gif';
        $file = new \Symfony\Component\HttpFoundation\File\File($path);

        try {
            $file->getMimeType();
        } catch (\LogicException $e) {
            $this->markTestSkipped('Guessing the mime type is not possible');
        }

        $constraint = new File(mimeTypesMessage: 'myMessage', extensions: ['gif', 'txt']);

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameters([
                '{{ file }}' => '"'.$path.'"',
                '{{ name }}' => '"invalid-content.gif"',
                '{{ type }}' => '"text/plain"',
                '{{ types }}' => '"image/gif"',
            ])
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testUploadedFileExtensions()
    {
        $file = new UploadedFile(__DIR__.'/Fixtures/bar', 'bar.txt', 'text/plain', \UPLOAD_ERR_OK, true);

        try {
            $file->getMimeType();
        } catch (\LogicException $e) {
            $this->markTestSkipped('Guessing the mime type is not possible');
        }

        $constraint = new File(mimeTypesMessage: 'myMessage', extensions: ['txt']);

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideFilenameMaxLengthIsTooLong
     */
    public function testFilenameMaxLengthIsTooLong(File $constraintFile, string $messageViolation)
    {
        file_put_contents($this->path, '1');

        $file = new UploadedFile($this->path, 'myFileWithATooLongOriginalFileName', null, null, true);
        $this->validator->validate($file, $constraintFile);

        $this->buildViolation($messageViolation)
            ->setParameters([
                '{{ filename_max_length }}' => $constraintFile->filenameMaxLength,
            ])
            ->setCode(File::FILENAME_TOO_LONG)
            ->setPlural($constraintFile->filenameMaxLength)
            ->assertRaised();
    }

    public static function provideFilenameMaxLengthIsTooLong(): \Generator
    {
        yield 'Simple case with only the parameter "filenameMaxLength" ' => [
            new File(filenameMaxLength: 30),
            'The filename is too long. It should have {{ filename_max_length }} character or less.|The filename is too long. It should have {{ filename_max_length }} characters or less.',
        ];

        yield 'Case with the parameter "filenameMaxLength" and a custom error message' => [
            new File(filenameMaxLength: 20, filenameTooLongMessage: 'Your filename is too long. Please use at maximum {{ filename_max_length }} characters'),
            'Your filename is too long. Please use at maximum {{ filename_max_length }} characters',
        ];
    }

    public function testFilenameMaxLength()
    {
        file_put_contents($this->path, '1');

        $file = new UploadedFile($this->path, 'tinyOriginalFileName', null, null, true);
        $this->validator->validate($file, new File(filenameMaxLength: 20));

        $this->assertNoViolation();
    }

    abstract protected function getFile($filename);
}
