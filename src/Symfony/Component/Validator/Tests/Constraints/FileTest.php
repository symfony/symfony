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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class FileTest extends TestCase
{
    /**
     * @dataProvider provideValidSizes
     */
    public function testMaxSize($maxSize, $bytes, $binaryFormat)
    {
        $file = new File(['maxSize' => $maxSize]);

        $this->assertSame($bytes, $file->maxSize);
        $this->assertSame($binaryFormat, $file->binaryFormat);
        $this->assertTrue($file->__isset('maxSize'));
    }

    public function testMagicIsset()
    {
        $file = new File(['maxSize' => 1]);

        $this->assertTrue($file->__isset('maxSize'));
        $this->assertTrue($file->__isset('groups'));
        $this->assertFalse($file->__isset('toto'));
    }

    /**
     * @dataProvider provideValidSizes
     */
    public function testMaxSizeCanBeSetAfterInitialization($maxSize, $bytes, $binaryFormat)
    {
        $file = new File();
        $file->maxSize = $maxSize;

        $this->assertSame($bytes, $file->maxSize);
        $this->assertSame($binaryFormat, $file->binaryFormat);
    }

    /**
     * @dataProvider provideInvalidSizes
     */
    public function testInvalidValueForMaxSizeThrowsExceptionAfterInitialization($maxSize)
    {
        $this->expectException(ConstraintDefinitionException::class);
        $file = new File(['maxSize' => 1000]);
        $file->maxSize = $maxSize;
    }

    /**
     * @dataProvider provideInvalidSizes
     */
    public function testMaxSizeCannotBeSetToInvalidValueAfterInitialization($maxSize)
    {
        $file = new File(['maxSize' => 1000]);

        try {
            $file->maxSize = $maxSize;
        } catch (ConstraintDefinitionException $e) {
        }

        $this->assertSame(1000, $file->maxSize);
    }

    public function testFilenameMaxLength()
    {
        $file = new File(['filenameMaxLength' => 30]);
        $this->assertSame(30, $file->filenameMaxLength);
    }

    public function testDefaultFilenameCountUnitIsUsed()
    {
        $file = new File();
        self::assertSame(File::FILENAME_COUNT_CODEPOINTS, $file->filenameCountUnit);
    }

    public function testDefaultFilenameCharsetIsUsed()
    {
        $file = new File();
        self::assertSame('UTF-8', $file->filenameCharset);
    }

    public function testInvalidFilenameCountUnitThrowsException()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The "filenameCountUnit" option must be one of the "%s"::FILENAME_COUNT_* constants ("%s" given).', File::class, 'nonExistentCountUnit'));
        $file = new File(['filenameCountUnit' => 'nonExistentCountUnit']);
    }

    /**
     * @dataProvider provideInValidSizes
     */
    public function testInvalidMaxSize($maxSize)
    {
        $this->expectException(ConstraintDefinitionException::class);
        new File(['maxSize' => $maxSize]);
    }

    public static function provideValidSizes()
    {
        return [
            ['500', 500, false],
            [12300, 12300, false],
            ['1ki', 1024, true],
            ['1KI', 1024, true],
            ['2k', 2000, false],
            ['2K', 2000, false],
            ['1mi', 1048576, true],
            ['1MI', 1048576, true],
            ['3m', 3000000, false],
            ['3M', 3000000, false],
            ['1gi', 1073741824, true],
            ['1GI', 1073741824, true],
            ['2g', 2000000000, false],
            ['2G', 2000000000, false],
            ['4g', 4 === \PHP_INT_SIZE ? 4000000000.0 : 4000000000, false],
            ['4G', 4 === \PHP_INT_SIZE ? 4000000000.0 : 4000000000, false],
        ];
    }

    public static function provideInvalidSizes()
    {
        return [
            ['+100'],
            ['foo'],
            ['1Ko'],
            ['1kio'],
        ];
    }

    /**
     * @dataProvider provideFormats
     */
    public function testBinaryFormat($maxSize, $guessedFormat, $binaryFormat)
    {
        $file = new File(['maxSize' => $maxSize, 'binaryFormat' => $guessedFormat]);

        $this->assertSame($binaryFormat, $file->binaryFormat);
    }

    public static function provideFormats()
    {
        return [
            [100, null, false],
            [100, true, true],
            [100, false, false],
            ['100K', null, false],
            ['100K', true, true],
            ['100K', false, false],
            ['100Ki', null, true],
            ['100Ki', true, true],
            ['100Ki', false, false],
        ];
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(FileDummy::class);
        self::assertTrue((new AttributeLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertNull($aConstraint->maxSize);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(100, $bConstraint->maxSize);
        self::assertSame('myMessage', $bConstraint->notFoundMessage);
        self::assertSame(['Default', 'FileDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(100000, $cConstraint->maxSize);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
        self::assertSame(30, $cConstraint->filenameMaxLength);
        self::assertSame('ISO-8859-15', $cConstraint->filenameCharset);
        self::assertSame(File::FILENAME_COUNT_BYTES, $cConstraint->filenameCountUnit);
    }
}

class FileDummy
{
    #[File]
    private $a;

    #[File(maxSize: 100, notFoundMessage: 'myMessage')]
    private $b;

    #[File(maxSize: '100K', filenameMaxLength: 30, filenameCharset: 'ISO-8859-15', filenameCountUnit: File::FILENAME_COUNT_BYTES, groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
