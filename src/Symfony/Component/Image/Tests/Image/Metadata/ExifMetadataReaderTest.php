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
use Symfony\Component\Image\Image\Metadata\ExifMetadataReader;

class ExifMetadataReaderTest extends MetadataReaderTestCase
{
    protected function setUp()
    {
        parent::setUp();
        if (!function_exists('exif_read_data')) {
            $this->markTestSkipped('exif extension is not loaded');
        }
    }

    protected function getReader()
    {
        return new ExifMetadataReader();
    }

    public function testExifDataAreReadWithReadFile()
    {
        $metadata = $this->getReader()->readFile(FixturesLoader::getFixture('exifOrientation/90.jpg'));
        $this->assertTrue(isset($metadata['ifd0.Orientation']));
        $this->assertEquals(6, $metadata['ifd0.Orientation']);
    }

    public function testExifDataAreReadWithReadHttpFile()
    {
        $source = self::HTTP_IMAGE;

        $metadata = $this->getReader()->readFile($source);
        $this->assertEquals(null, $metadata['ifd0.Orientation']);
    }

    public function testExifDataAreReadWithReadData()
    {
        $metadata = $this->getReader()->readData(file_get_contents(FixturesLoader::getFixture('exifOrientation/90.jpg')));
        $this->assertTrue(isset($metadata['ifd0.Orientation']));
        $this->assertEquals(6, $metadata['ifd0.Orientation']);
    }

    public function testExifDataAreReadWithReadStream()
    {
        $metadata = $this->getReader()->readStream(fopen(FixturesLoader::getFixture('exifOrientation/90.jpg'), 'r'));
        $this->assertTrue(isset($metadata['ifd0.Orientation']));
        $this->assertEquals(6, $metadata['ifd0.Orientation']);
    }
}
