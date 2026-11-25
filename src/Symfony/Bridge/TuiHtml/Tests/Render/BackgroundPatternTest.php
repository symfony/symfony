<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

#[RequiresPhpExtension('gd')]
final class BackgroundPatternTest extends TestCase
{
    public function testRepeatedPngPaintsItsResolvedPatternBehindText()
    {
        $data = file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png');
        $uri = 'data:image/png;base64,'.base64_encode($data);
        $list = new HtmlRenderer()->render('<!doctype html><div style="width:8ch;height:2;background-image:url('.$uri.')">A</div>', $this->context())->getDisplayList();
        $screen = new ScreenBuffer(8, 2);
        $screen->write(implode("\r\n", new DisplayListRenderer()->render($list, 8, 2)));
        $cells = $screen->getCells();
        self::assertSame('A', $cells[0][0]['char']);
        self::assertStringContainsString('48;2;255;0;0', $cells[0][0]['style']);
        self::assertStringContainsString('48;2;0;255;0', $cells[0][2]['style']);
        self::assertStringContainsString('48;2;0;0;255', $cells[1][0]['style']);
        self::assertSame('', $cells[1][2]['style']);
        self::assertStringContainsString('48;2;255;0;0', $cells[0][4]['style']);
    }

    public function testSvgNoRepeatPlacementAndClippingUseOnlyTheNumericPattern()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect width="16" height="16" fill="red"/></svg>';
        $uri = 'data:image/svg+xml,'.rawurlencode($svg);
        $list = new HtmlRenderer()->render('<!doctype html><div style="width:5ch;height:2;background:blue url('.$uri.') right bottom no-repeat"></div>', $this->context())->getDisplayList();
        $screen = new ScreenBuffer(5, 2);
        $screen->write(implode("\r\n", new DisplayListRenderer()->render($list, 5, 2)));
        $cells = $screen->getCells();
        self::assertStringContainsString('44', $cells[0][0]['style']);
        self::assertStringContainsString('48;2;255;0;0', $cells[1][3]['style']);
        self::assertStringContainsString('48;2;255;0;0', $cells[1][4]['style']);
        self::assertStringNotContainsString('48;2;255;0;0', $cells[0][3]['style']);
        $forced = new DisplayListRenderer(new TerminalTextMeasurer(), new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, true));
        self::assertSame('', trim(implode('', $forced->render($list, 5, 2))));
    }

    public function testIntrinsicImageGeometryKeepsTheSameCellCountsAtDifferentDensities()
    {
        $image = imagecreatetruecolor(17, 37);
        ob_start();
        imagepng($image);
        $uri = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        foreach ([[8.0, 16.0], [16.0, 32.0]] as [$width, $height]) {
            $measurer = new TerminalTextMeasurer($width, $height);
            $context = new RenderingContext(80 * $width, 24 * $height, $measurer, null, new TerminalLayoutMetrics($measurer));
            $operations = new HtmlRenderer()->render('<!doctype html><div style="width:6ch;height:4;background-image:url('.$uri.');background-position:center top"></div>', $context)->getDisplayList()->getOperations();
            self::assertSame(3 * $width, $operations[0]['pattern']['x']['size']);
            self::assertSame(3 * $height, $operations[0]['pattern']['y']['size']);
            self::assertSame(2 * $width, $operations[0]['pattern']['x']['start']);
        }
    }

    public function testTransparentImageHalvesPreserveBothPixelsOfEarlierLayers()
    {
        $sources = [];
        foreach ([false, true] as $transparent) {
            $image = imagecreatetruecolor(2, 2);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $top = $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 255, 0, 0);
            $bottom = $transparent ? imagecolorallocate($image, 255, 255, 0) : imagecolorallocate($image, 0, 0, 255);
            imagefilledrectangle($image, 0, 0, 1, 0, $top);
            imagefilledrectangle($image, 0, 1, 1, 1, $bottom);
            ob_start();
            imagepng($image);
            $sources[] = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        }
        $list = new HtmlRenderer()->render('<!doctype html><div style="width:1ch;height:1;background-image:url('.$sources[1].'),url('.$sources[0].')"></div>', $this->context())->getDisplayList();
        $screen = new ScreenBuffer(1, 1);
        $screen->write(implode('', new DisplayListRenderer()->render($list, 1, 1)));
        self::assertSame('▀', $screen->getCells()[0][0]['char']);
        self::assertStringContainsString('38;2;255;0;0;48;2;255;255;0', $screen->getCells()[0][0]['style']);
    }

    public function testFullyTransparentLayerRetainsExistingGlyphAndBothColors()
    {
        $image = imagecreatetruecolor(1, 1);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagesetpixel($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start();
        imagepng($image);
        $uri = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        $operations = new HtmlRenderer()->render('<!doctype html><div style="width:1ch;height:1;background-image:url('.$uri.')"></div>', $this->context())->getDisplayList()->getOperations();
        $text = ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'A', 'color' => 'red', 'font-size' => 16.0];
        $background = ['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => 8.0, 'height' => 16.0, 'color' => 'blue'];
        $base = new DisplayListRenderer()->render(new DisplayList([$background, $text]), 1, 1);
        self::assertSame($base, new DisplayListRenderer()->render(new DisplayList([$background, $text, ...$operations]), 1, 1));
    }

    public function testTextClipPaintsGlyphsWithSharedTextStylesInsteadOfBackgroundCells()
    {
        $data = file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png');
        $uri = 'data:image/png;base64,'.base64_encode($data);
        $list = new HtmlRenderer()->render('<!doctype html><div style="width:4ch;height:1;background-image:url('.$uri.');background-clip:text;-webkit-text-fill-color:transparent;font-weight:700;font-style:italic">ABCD</div>', $this->context())->getDisplayList();
        $screen = new ScreenBuffer(4, 1);
        $screen->write(implode('', new DisplayListRenderer()->render($list, 4, 1)));
        $cells = $screen->getCells()[0];
        self::assertSame(['A', 'B', 'C', 'D'], array_column($cells, 'char'));
        self::assertStringContainsString('38;2;255;0;0', $cells[0]['style']);
        self::assertStringContainsString('38;2;0;255;0', $cells[2]['style']);
        self::assertStringContainsString('1;', $cells[0]['style']);
        self::assertStringContainsString('3', $cells[0]['style']);
        self::assertStringNotContainsString('48;', $cells[0]['style']);
    }

    public function testTextClipUsesTranslatedDescendantClipsAndExplicitRotationApproximation()
    {
        foreach (['translateX(8px)', 'rotate(20deg)'] as $transform) {
            $list = new HtmlRenderer()->render('<!doctype html><div style="width:4ch;background:linear-gradient(red,red);background-clip:text;-webkit-text-fill-color:transparent"><span style="display:inline-block;width:1ch;overflow:hidden;transform:'.$transform.'">AB</span></div>', $this->context())->getDisplayList();
            $screen = new ScreenBuffer(4, 2);
            $screen->write(implode("\r\n", new DisplayListRenderer()->render($list, 4, 2)));
            $column = 'translateX(8px)' === $transform ? 1 : 0;
            self::assertSame('A', $screen->getCells()[0][$column]['char']);
            self::assertSame(' ', $screen->getCells()[0][$column + 1]['char']);
        }
    }

    private function context(): RenderingContext
    {
        $measurer = new TerminalTextMeasurer();

        return new RenderingContext(640, 384, $measurer, null, new TerminalLayoutMetrics($measurer));
    }
}
