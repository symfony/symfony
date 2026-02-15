<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Terminal\Image;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Terminal\Image\KittyGraphicsProtocol;

class KittyGraphicsProtocolTest extends TestCase
{
    public function testGetName()
    {
        $this->assertSame('kitty', (new KittyGraphicsProtocol())->getName());
    }

    public function testDetectPastedImageWithKittySequence()
    {
        $data = "some text\x1b_Ga=T,f=100;base64data\x1b\\more text";

        $this->assertTrue((new KittyGraphicsProtocol())->detectPastedImage($data));
    }

    public function testDetectPastedImageWithoutKittySequence()
    {
        $data = 'just plain text';

        $this->assertFalse((new KittyGraphicsProtocol())->detectPastedImage($data));
    }

    public function testDecodeValidPayload()
    {
        $imageData = 'test image data';
        $base64 = base64_encode($imageData);
        $data = "\x1b_Ga=T,f=100;{$base64}\x1b\\";

        $result = (new KittyGraphicsProtocol())->decode($data);

        $this->assertSame($imageData, $result['data']);
        $this->assertSame('png', $result['format']);
    }

    public function testDecodeWithBellTerminator()
    {
        $imageData = 'test image data';
        $base64 = base64_encode($imageData);
        $data = "\x1b_Ga=T,f=100;{$base64}\x07";

        $result = (new KittyGraphicsProtocol())->decode($data);

        $this->assertSame($imageData, $result['data']);
    }

    public function testDecodeInvalidBase64()
    {
        $data = "\x1b_Ga=T,f=100;not-valid-base64!!!\x1b\\";

        $result = (new KittyGraphicsProtocol())->decode($data);

        $this->assertSame('', $result['data']);
        $this->assertNull($result['format']);
    }

    public function testDecodeWithNoPayload()
    {
        $data = 'just text';

        $result = (new KittyGraphicsProtocol())->decode($data);

        $this->assertSame('', $result['data']);
        $this->assertNull($result['format']);
    }

    public function testDecodeWithNoTerminator()
    {
        $data = "\x1b_Ga=T,f=100;".base64_encode('test');

        $result = (new KittyGraphicsProtocol())->decode($data);

        $this->assertSame('', $result['data']);
    }

    public function testDecodeWithNoSemicolon()
    {
        $data = "\x1b_Ga=T,f=100\x1b\\";

        $result = (new KittyGraphicsProtocol())->decode($data);

        $this->assertSame('', $result['data']);
    }

    public function testEncode()
    {
        $imageData = 'test image data';

        $encoded = (new KittyGraphicsProtocol())->encode($imageData);

        $this->assertStringStartsWith("\x1b_G", $encoded);
        $this->assertStringEndsWith("\x1b\\", $encoded);
        $this->assertStringContainsString(base64_encode($imageData), $encoded);
    }

    public function testEncodeWithMaxWidth()
    {
        $imageData = 'test image data';
        $encoded = (new KittyGraphicsProtocol())->encode($imageData, 50);

        $this->assertStringContainsString('c=50', $encoded);
    }

    public function testEncodePngFormat()
    {
        $pngData = "\x89PNG\r\n\x1a\n".str_repeat("\x00", 10);

        $encoded = (new KittyGraphicsProtocol())->encode($pngData);

        $this->assertStringContainsString('f=100', $encoded);
    }

    public function testDecodeDifferentFormats()
    {
        $data = "\x1b_Gf=24;".base64_encode('rgb data')."\x1b\\";
        $result = (new KittyGraphicsProtocol())->decode($data);
        $this->assertSame('rgb', $result['format']);

        $data = "\x1b_Gf=32;".base64_encode('rgba data')."\x1b\\";
        $result = (new KittyGraphicsProtocol())->decode($data);
        $this->assertSame('rgba', $result['format']);
    }
}
