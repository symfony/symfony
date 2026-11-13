<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

#[RequiresPhpExtension('gd')]
final class ImageRenderingTest extends TestCase
{
    public function testAPhotoTakenSidewaysStandsUp()
    {
        $src = self::sideways();

        // 32 by 16 pixels stored, 16 by 32 shown: 2 columns and 2 rows, not 4 columns and 1 row
        [$lines, $output] = $this->render('<img src="'.$src.'" alt="photo">');
        $this->assertSame(['▀▀', '▀▀'], $lines);
        $this->assertMatchesRegularExpression('/^[^▀]*\e\[38;2;2[0-5]\d;\d{1,2};\d{1,2};/', $output, 'Red at the top: the left of the stored image.');

        $this->assertSame(['▀▀▀▀'], $this->render('<img src="'.$src.'" alt="photo" style="image-orientation: none">')[0], 'As stored.');
        $this->assertSame(['▀▀▀▀'], $this->render('<div style="image-orientation: none"><img src="'.$src.'" alt="photo"></div>')[0], 'Inherited.');
    }

    public function testPixelatedKeepsThePixelsSharp()
    {
        $image = imagecreatetruecolor(2, 2);
        imagefilledrectangle($image, 0, 0, 0, 1, imagecolorallocate($image, 255, 0, 0) ?: 0);
        imagefilledrectangle($image, 1, 0, 1, 1, imagecolorallocate($image, 0, 0, 255) ?: 0);
        ob_start();
        imagepng($image);
        $src = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $output = $this->render('<img src="'.$src.'" alt="dots" style="width: 3ch; image-rendering: pixelated">')[1];
        preg_match_all('/38;2;(\d+);(\d+);(\d+)/', $output, $colors, \PREG_SET_ORDER);
        $this->assertNotEmpty($colors);
        foreach ($colors as [, $r, , $b]) {
            $this->assertContains([(int) $r, (int) $b], [[255, 0], [0, 255]], 'Every cell one pixel or the other.');
        }
        $this->assertMatchesRegularExpression('/38;2;(?!255;0;0|0;0;255)\d+;\d+;\d+/', $this->render('<img src="'.$src.'" alt="dots" style="width: 3ch">')[1], 'Smooth: a blend between them.');
    }

    public function testImagesThatCannotBeDrawnAreFlexItemsOfTheirOwn()
    {
        // Like a gallery of screenshots without an image loader: a box each, apart, not one line of text
        $this->assertSame(['┌───┐ ┌───┐', '│[a]│ │[b]│', '└───┘ └───┘'], $this->render('<div style="display: flex; gap: 1ch"><img src="a.webp" alt="a" style="border: 1px solid"><img src="b.webp" alt="b" style="border: 1px solid"></div>')[0]);
    }

    /**
     * A JPEG 32 pixels wide and 16 tall, red on the left, blue on the right, taken turned: its EXIF data says to turn it clockwise.
     */
    private static function sideways(): string
    {
        $image = imagecreatetruecolor(32, 16);
        imagefilledrectangle($image, 0, 0, 15, 15, imagecolorallocate($image, 255, 0, 0) ?: 0);
        imagefilledrectangle($image, 16, 0, 31, 15, imagecolorallocate($image, 0, 0, 255) ?: 0);
        ob_start();
        imagejpeg($image, null, 100);
        $jpeg = (string) ob_get_clean();
        $tiff = 'MM'.pack('nN', 42, 8).pack('n', 1).pack('nnNnn', 0x0112, 3, 1, 6, 0).pack('N', 0);
        $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', 8 + \strlen($tiff))."Exif\0\0".$tiff.substr($jpeg, 2);

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    /**
     * @return array{list<string>, string}
     */
    private function render(string $html): array
    {
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();
        $buffer = new ScreenBuffer(20, 6);
        $buffer->write($output);

        return [array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line)), $output];
    }
}
