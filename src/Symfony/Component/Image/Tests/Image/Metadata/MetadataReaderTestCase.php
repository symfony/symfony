<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image\Metadata;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Metadata\MetadataReaderInterface;
use Symfony\Component\Image\Tests\TestCase;

/**
 */
abstract class MetadataReaderTestCase extends TestCase
{
    /**
     * @return MetadataReaderInterface
     */
    abstract protected function getReader();

    public function testReadFromFile()
    {
        $source = FixturesLoader::getFixture('pixel-CMYK.jpg');
        $metadata = $this->getReader()->readFile($source);
        $this->assertInstanceOf(MetadataBag::class, $metadata);
        $this->assertEquals(realpath($source), $metadata['filepath']);
        $this->assertEquals($source, $metadata['uri']);
    }

    public function testReadFromExifUncompatibleFile()
    {
        $source = FixturesLoader::getFixture('trans.png');
        $metadata = $this->getReader()->readFile($source);
        $this->assertInstanceOf(MetadataBag::class, $metadata);
        $this->assertEquals(realpath($source), $metadata['filepath']);
        $this->assertEquals($source, $metadata['uri']);
    }

    public function testReadFromHttpFile()
    {
        $source = self::HTTP_IMAGE;
        $metadata = $this->getReader()->readFile($source);
        $this->assertInstanceOf(MetadataBag::class, $metadata);
        $this->assertFalse(isset($metadata['filepath']));
        $this->assertEquals($source, $metadata['uri']);
    }

    /**
     * @expectedException \Symfony\Component\Image\Exception\InvalidArgumentException
     * @expectedExceptionMessage File /path/to/no/file does not exist.
     */
    public function testReadFromInvalidFileThrowsAnException()
    {
        $this->getReader()->readFile('/path/to/no/file');
    }

    public function testReadFromData()
    {
        $source = FixturesLoader::getFixture('pixel-CMYK.jpg');
        $metadata = $this->getReader()->readData(file_get_contents($source));
        $this->assertInstanceOf(MetadataBag::class, $metadata);
    }

    public function testReadFromInvalidDataDoesNotThrowException()
    {
        $metadata = $this->getReader()->readData('this is nonsense');
        $this->assertInstanceOf(MetadataBag::class, $metadata);
    }

    public function testReadFromStream()
    {
        $source = FixturesLoader::getFixture('pixel-CMYK.jpg');
        $resource = fopen($source, 'r');
        $metadata = $this->getReader()->readStream($resource);
        $this->assertInstanceOf(MetadataBag::class, $metadata);
        $this->assertEquals(realpath($source), $metadata['filepath']);
        $this->assertEquals($source, $metadata['uri']);
    }

    /**
     * @expectedException \Symfony\Component\Image\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid resource provided.
     */
    public function testReadFromInvalidStreamThrowsAnException()
    {
        $metadata = $this->getReader()->readStream(false);
        $this->assertInstanceOf(MetadataBag::class, $metadata);
    }
}
