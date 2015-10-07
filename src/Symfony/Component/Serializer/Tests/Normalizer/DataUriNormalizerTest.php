<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DataUriNormalizerTest extends \PHPUnit_Framework_TestCase
{
    const TEST_GIF_DATA = 'data:image/gif;base64,R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=';
    const TEST_TXT_DATA = 'data:text/plain,K%C3%A9vin%20Dunglas%0A';
    const TEST_TXT_CONTENT = "Kévin Dunglas\n";

    /**
     * @var DataUriNormalizer
     */
    private $normalizer;

    public function setUp()
    {
        $this->normalizer = new DataUriNormalizer();
    }

    public function testInterface()
    {
        $this->assertInstanceOf('Symfony\Component\Serializer\Normalizer\NormalizerInterface', $this->normalizer);
        $this->assertInstanceOf('Symfony\Component\Serializer\Normalizer\DenormalizerInterface', $this->normalizer);
    }

    public function testSupportNormalization()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
        $this->assertTrue($this->normalizer->supportsNormalization(new \SplFileObject('data:,Hello%2C%20World!')));
    }

    public function testNormalizeHttpFoundationFile()
    {
        $file = new File(__DIR__.'/../Fixtures/test.gif');

        $this->assertSame(self::TEST_GIF_DATA, $this->normalizer->normalize($file));
    }

    public function testNormalizeSplFileInfo()
    {
        $file = new \SplFileInfo(__DIR__.'/../Fixtures/test.gif');

        $this->assertSame(self::TEST_GIF_DATA, $this->normalizer->normalize($file));
    }

    public function testNormalizeText()
    {
        $file = new \SplFileObject(__DIR__.'/../Fixtures/test.txt');

        $data = $this->normalizer->normalize($file);

        $this->assertSame(self::TEST_TXT_DATA, $data);
        $this->assertSame(self::TEST_TXT_CONTENT, file_get_contents($data));
    }

    public function testSupportsDenormalization()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization('foo', 'Bar'));
        $this->assertTrue($this->normalizer->supportsDenormalization(self::TEST_GIF_DATA, 'SplFileInfo'));
        $this->assertTrue($this->normalizer->supportsDenormalization(self::TEST_GIF_DATA, 'SplFileObject'));
        $this->assertTrue($this->normalizer->supportsDenormalization(self::TEST_TXT_DATA, 'Symfony\Component\HttpFoundation\File\File'));
    }

    public function testDenormalizeSplFileInfo()
    {
        $file = $this->normalizer->denormalize(self::TEST_TXT_DATA, 'SplFileInfo');

        $this->assertInstanceOf('SplFileInfo', $file);
        $this->assertEquals(new \SplFileObject(self::TEST_TXT_DATA), $file);
    }

    public function testDenormalizeSplFileObject()
    {
        $file = $this->normalizer->denormalize(self::TEST_TXT_DATA, 'SplFileObject');

        $this->assertInstanceOf('SplFileObject', $file);
        $this->assertEquals(new \SplFileObject(self::TEST_TXT_DATA), $file);
    }

    public function testDenormalizeHttpFoundationFile()
    {
        $file = $this->normalizer->denormalize(self::TEST_GIF_DATA, 'Symfony\Component\HttpFoundation\File\File');

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\File\File', $file);
        $this->assertEquals(new File(self::TEST_TXT_DATA, false), $file);
    }
}
