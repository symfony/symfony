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
use Symfony\Component\Console\Terminal\Image\ITerm2Protocol;

class ITerm2ProtocolTest extends TestCase
{
    public function testGetName()
    {
        $this->assertSame('iterm2', (new ITerm2Protocol())->getName());
    }

    public function testDetectPastedImageWithITerm2Sequence()
    {
        $data = "some text\x1b]1337;File=inline=1:base64data\x07more text";

        $this->assertTrue((new ITerm2Protocol())->detectPastedImage($data));
    }

    public function testDetectPastedImageWithoutITerm2Sequence()
    {
        $data = 'just plain text';

        $this->assertFalse((new ITerm2Protocol())->detectPastedImage($data));
    }

    public function testDecodeValidPayload()
    {
        $imageData = 'test image data';
        $base64 = base64_encode($imageData);
        $data = "\x1b]1337;File=inline=1:{$base64}\x07";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame($imageData, $result['data']);
    }

    public function testDecodeWithStTerminator()
    {
        $imageData = 'test image data';
        $base64 = base64_encode($imageData);
        $data = "\x1b]1337;File=inline=1:{$base64}\x1b\\";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame($imageData, $result['data']);
    }

    public function testDecodeInvalidBase64()
    {
        $data = "\x1b]1337;File=inline=1:not-valid-base64!!!\x07";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('', $result['data']);
        $this->assertNull($result['format']);
    }

    public function testDecodeWithNoPayload()
    {
        $data = 'just text';

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('', $result['data']);
        $this->assertNull($result['format']);
    }

    public function testDecodeWithNoTerminator()
    {
        $data = "\x1b]1337;File=inline=1:".base64_encode('test');

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('', $result['data']);
    }

    public function testDecodeWithNoColon()
    {
        $data = "\x1b]1337;File=inline=1\x07";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('', $result['data']);
    }

    public function testEncode()
    {
        $imageData = 'test image data';

        $encoded = (new ITerm2Protocol())->encode($imageData);

        $this->assertStringStartsWith("\x1b]1337;File=", $encoded);
        $this->assertStringEndsWith("\x07", $encoded);
        $this->assertStringContainsString('inline=1', $encoded);
        $this->assertStringContainsString(base64_encode($imageData), $encoded);
    }

    public function testEncodeWithMaxWidth()
    {
        $imageData = 'test image data';

        $encoded = (new ITerm2Protocol())->encode($imageData, 50);

        $this->assertStringContainsString('width=50', $encoded);
    }

    public function testEncodePreservesAspectRatio()
    {
        $imageData = 'test image data';

        $encoded = (new ITerm2Protocol())->encode($imageData);

        $this->assertStringContainsString('preserveAspectRatio=1', $encoded);
    }

    public function testDecodeDetectsPngFormat()
    {
        $pngData = "\x89PNG\r\n\x1a\n".str_repeat("\x00", 10);
        $base64 = base64_encode($pngData);
        $data = "\x1b]1337;File=inline=1:{$base64}\x07";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('png', $result['format']);
    }

    public function testDecodeDetectsJpegFormat()
    {
        $jpegData = "\xFF\xD8\xFF".str_repeat("\x00", 10);
        $base64 = base64_encode($jpegData);
        $data = "\x1b]1337;File=inline=1:{$base64}\x07";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('jpg', $result['format']);
    }

    public function testDecodeDetectsGifFormat()
    {
        $gifData = 'GIF89a'.str_repeat("\x00", 10);
        $base64 = base64_encode($gifData);
        $data = "\x1b]1337;File=inline=1:{$base64}\x07";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('gif', $result['format']);
    }

    public function testDecodeDetectsWebpFormat()
    {
        $webpData = "RIFF\x00\x00\x00\x00WEBP".str_repeat("\x00", 10);
        $base64 = base64_encode($webpData);
        $data = "\x1b]1337;File=inline=1:{$base64}\x07";

        $result = (new ITerm2Protocol())->decode($data);

        $this->assertSame('webp', $result['format']);
    }
}
