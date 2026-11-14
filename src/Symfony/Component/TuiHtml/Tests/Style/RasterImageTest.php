<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Style\RasterImage;

#[RequiresPhpExtension('gd')]
final class RasterImageTest extends TestCase
{
    public function testTheExifOrientationIsApplied()
    {
        // 32 by 16, red on the left, blue on the right, to be turned clockwise: it stands 16 by 32, red at the top
        $image = new RasterImage(self::jpeg(6));
        $this->assertSame(16, $image->getWidth());
        $this->assertSame(32, $image->getHeight());
        $this->assertTrue(self::isRed($image->cells(1, 2)[0][0][0]), 'Red at the top.');
        $this->assertFalse(self::isRed($image->cells(1, 2)[1][0][1]), 'Blue at the bottom.');
        $stored = $image->withRendering(false);
        $this->assertSame([32, 16], [$stored->getWidth(), $stored->getHeight()], 'image-orientation: none, as stored.');
        $this->assertTrue(self::isRed($stored->cells(2, 1)[0][0][0]));
        $this->assertSame($stored, $image->withRendering(false), 'The variant is kept.');
        $this->assertSame(32, $stored->withRendering()->getHeight(), 'Its default variant turned again.');
    }

    public function testAVariantKeepsTheResolution()
    {
        $image = new RasterImage(self::jpeg(null));
        $image->withRendering(true, true);
        $this->assertSame(2.0, $image->withResolution(2.0)->withRendering(true, true)->getResolution());
    }

    public function testItsSizeIsReadFromItsHeaderWithoutDecodingIt()
    {
        $image = new RasterImage(self::jpeg(6));
        $this->assertSame([16, 32], $image->peekSize(), 'Turned as its EXIF orientation says.');
        $this->assertNull((new \ReflectionProperty(RasterImage::class, 'image'))->getValue($image), 'Not decoded.');
        $this->assertSame([32, 16], $image->withRendering(false)->peekSize());
        $this->assertNull((new RasterImage('not an image'))->peekSize());
        $this->assertNull((new RasterImage('<svg xmlns="http://www.w3.org/2000/svg"/>'))->peekSize(), 'Not one GD reads.');
    }

    #[DataProvider('provideOrientations')]
    public function testEachOrientation(int $orientation, int $width, bool $redFirst)
    {
        $image = new RasterImage(self::jpeg($orientation));
        $this->assertSame($width, $image->getWidth());
        // The first cell of the first row: red where the red half of the image ends up, at the left or the top
        $this->assertSame($redFirst, self::isRed($image->cells(2, 2)[0][0][0]));
    }

    public static function provideOrientations(): iterable
    {
        yield 'upright' => [1, 32, true];
        yield 'flipped' => [2, 32, false];
        yield 'half a turn' => [3, 32, false];
        yield 'upside down' => [4, 32, true];
        yield 'transposed' => [5, 16, true];
        yield 'turned clockwise' => [6, 16, true];
        yield 'transversed' => [7, 16, false];
        yield 'turned counterclockwise' => [8, 16, false];
    }

    public function testWithoutExifDataNothingTurns()
    {
        $this->assertSame(32, (new RasterImage(self::jpeg(null)))->getWidth());
    }

    public function testPixelatedKeepsTheNearestPixel()
    {
        $image = imagecreatetruecolor(2, 1);
        imagesetpixel($image, 0, 0, imagecolorallocate($image, 255, 0, 0) ?: 0);
        imagesetpixel($image, 1, 0, imagecolorallocate($image, 0, 0, 255) ?: 0);
        ob_start();
        imagepng($image);
        $png = new RasterImage((string) ob_get_clean());
        // The middle one of 3 columns over 2 pixels: half of each
        $smooth = $png->cells(3, 1)[0][1][0];
        $sharp = $png->withRendering(true, true)->cells(3, 1)[0][1][0];
        $this->assertNotSame([255, 0, 0], $smooth, 'Smooth: blended with its neighbor.');
        $this->assertContains($sharp, [[255, 0, 0], [0, 0, 255]], 'Pixelated: one pixel or the other, not a blend.');
    }

    public function testACellOfALargeSizeIsReadWithoutTurningTheImageIntoCells()
    {
        $image = imagecreatetruecolor(4, 4);
        imagefilledrectangle($image, 0, 0, 1, 3, imagecolorallocate($image, 255, 0, 0) ?: 0);
        imagefilledrectangle($image, 2, 0, 3, 3, imagecolorallocate($image, 0, 0, 255) ?: 0);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        // Past the size cellAt() turns into cells: red on the left, blue on the right, like cells()
        $image = new RasterImage($png);
        $this->assertTrue(self::isRed($image->cellAt(300, 300, 10, 5)[0]));
        $this->assertSame([0, 0, 255], $image->cellAt(300, 300, 290, 299)[1]);
        $this->assertSame([null, null], $image->cellAt(300, 300, 300, 0), 'Out of the image: none.');
        $this->assertSame((new RasterImage($png))->cells(8, 8)[3][1], $image->cellAt(8, 8, 1, 3), 'A small size: its cells.');
        // The border of a box of 10,000 rows painted by an image: its pixels, not 1.2 million cells
        $image = new RasterImage($png);
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $this->assertTrue(self::isRed($image->cellAt(120, 10000, 0, 9999)[1]));
        $this->assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    public function testTheRowsOfALargeSizeAreReadOneAfterTheOther()
    {
        $image = imagecreatetruecolor(4, 4);
        imagefilledrectangle($image, 0, 0, 1, 3, imagecolorallocate($image, 255, 0, 0) ?: 0);
        imagefilledrectangle($image, 2, 0, 3, 3, imagecolorallocate($image, 0, 0, 255) ?: 0);
        ob_start();
        imagepng($image);
        $png = new RasterImage((string) ob_get_clean());
        $this->assertSame($png->cells(8, 4), iterator_to_array($png->cellRows(8, 4)), 'A small size: its cells.');
        // An image drawn a thousand columns wide and as many rows: a row at a time, not a million cells kept
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $rows = 0;
        foreach ($png->cellRows(1000, 1000) as $row => $cells) {
            $this->assertSame($rows++, $row);
            $this->assertCount(1000, $cells);
        }
        $this->assertSame(1000, $rows);
        $this->assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    private static function isRed(?array $pixel): bool
    {
        return null !== $pixel && $pixel[0] > 200 && $pixel[2] < 80;
    }

    private static function jpeg(?int $orientation): string
    {
        $image = imagecreatetruecolor(32, 16);
        imagefilledrectangle($image, 0, 0, 15, 15, imagecolorallocate($image, 255, 0, 0) ?: 0);
        imagefilledrectangle($image, 16, 0, 31, 15, imagecolorallocate($image, 0, 0, 255) ?: 0);
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = (string) ob_get_clean();
        if (null === $orientation) {
            return $jpeg;
        }
        // Big-endian TIFF, one IFD with one entry: Orientation, a SHORT
        $tiff = 'MM'.pack('nN', 42, 8).pack('n', 1).pack('nnNnn', 0x112, 3, 1, $orientation, 0).pack('N', 0);
        $app1 = "\xff\xe1".pack('n', 2 + 6 + \strlen($tiff))."Exif\x00\x00".$tiff;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }
}
