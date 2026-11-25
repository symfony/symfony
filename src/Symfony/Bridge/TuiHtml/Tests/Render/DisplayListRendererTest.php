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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\TextMarkers;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\Bidi;

final class DisplayListRendererTest extends TestCase
{
    public function testScaledProtocolPayloadUsesTheConfiguredColorDepth()
    {
        $text = ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'width' => 16.0, 'height' => 32.0, 'text' => 'A', 'color' => '#123456', 'font-size' => 32.0];
        $lines = (new DisplayListRenderer(new TerminalTextMeasurer(), new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, null, false, 16, true)))->render(new DisplayList([$text]), 4, 3);
        preg_match('/\x1b_pi:s;([^\x07]*)\x07/', $lines[0], $match);
        $payload = base64_decode($match[1]);
        $this->assertStringContainsString("\x1b]66;s=2;A\x07", $payload);
        $this->assertStringNotContainsString('38;2;', $payload);
        $this->assertStringNotContainsString('38;5;', $payload);
    }

    public function testScaledTextUsesWholeGlyphClippingAndRealCellBufferOcclusion()
    {
        $text = ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'width' => 48.0, 'height' => 32.0, 'text' => 'ABC', 'color' => 'red', 'font-size' => 32.0];
        $renderer = new DisplayListRenderer(new TerminalTextMeasurer(), new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, null, false, MediaContext::TRUE_COLORS, true));
        $clip = ['type' => 'push-clip', 'x' => 8.0, 'y' => 0.0, 'width' => 24.0, 'height' => 32.0];
        $lines = $renderer->render(new DisplayList([$clip, $text, ['type' => 'pop-clip']]), 6, 3);
        $this->assertSame(1, substr_count(implode('', $lines), TextMarkers::SCALED_PREFIX));
        preg_match('/\x1b_pi:s;([^\x07]*)\x07/', $lines[0], $match);
        $this->assertStringContainsString("\x1b]66;s=2;B\x07", base64_decode($match[1]));
        $covered = $renderer->render(new DisplayList([$clip, $text, ['type' => 'pop-clip'], ['type' => 'rectangle', 'x' => 16.0, 'y' => 16.0, 'width' => 8.0, 'height' => 16.0, 'color' => 'blue']]), 6, 3);
        $this->assertStringNotContainsString(TextMarkers::SCALED_PREFIX, implode('', $covered));
        $fallback = (new DisplayListRenderer())->render(new DisplayList([$text]), 6, 3);
        $this->assertSame('ABC', rtrim(AnsiUtils::stripAnsiCodes($fallback[0])));
        $this->assertStringNotContainsString(TextMarkers::SCALED_PREFIX, implode('', $fallback));
    }

    public function testOpacityGroupsPreserveTerminalDimmingAndZeroVisibility()
    {
        $list = new DisplayList([
            ['type' => 'push-opacity', 'opacity' => 0.5],
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'Dim', 'color' => 'green'],
            ['type' => 'push-opacity', 'opacity' => 0.0],
            ['type' => 'text', 'x' => 32.0, 'y' => 0.0, 'text' => 'Hidden', 'color' => 'green'],
            ['type' => 'pop-opacity'],
            ['type' => 'pop-opacity'],
            ['type' => 'text', 'x' => 0.0, 'y' => 16.0, 'text' => 'Normal', 'color' => 'green'],
        ]);
        $lines = (new DisplayListRenderer())->render($list, 20, 2);
        $screen = new \Symfony\Component\Tui\Terminal\ScreenBuffer(20, 2);
        $screen->write(implode("\r\n", $lines));
        $cells = $screen->getCells();
        $this->assertStringContainsString('2;', $cells[0][0]['style']);
        $this->assertStringNotContainsString('2;', $cells[1][0]['style']);
        $this->assertStringNotContainsString('Hidden', $screen->getScreen());
    }

    public function testFractionalBackgroundOriginDoesNotAddAnExtraCellRow()
    {
        $list = new DisplayList([
            ['type' => 'rectangle', 'x' => 4.0, 'y' => 8.0, 'width' => 16.0, 'height' => 16.0, 'color' => 'blue'],
        ]);
        $lines = (new DisplayListRenderer())->render($list, 3, 2);
        $screen = new \Symfony\Component\Tui\Terminal\ScreenBuffer(3, 2);
        $screen->write(implode("\r\n", $lines));
        $cells = $screen->getCells();
        $this->assertStringContainsString('44', $cells[0][0]['style']);
        $this->assertStringNotContainsString('44', $cells[1][0]['style']);
        $this->assertStringContainsString('44', $cells[0][1]['style']);
        $this->assertStringNotContainsString('44', $cells[0][2]['style']);
    }

    public function testResolvedBorderGradientPaintsGlyphsWithoutChangingInteriorText()
    {
        $session = (new \Symfony\Component\HtmlRenderer\HtmlRenderer())->createSession('<div style="width:8ch;height:3lh;border:1px solid;border-image-source:linear-gradient(to right,#ff0000,#0000ff);color:#00ff00">Text</div>', new \Symfony\Component\HtmlRenderer\RenderingContext(160, 64));
        $lines = (new \Symfony\Bridge\TuiHtml\Widget\DocumentWidget($session))->render(new \Symfony\Component\Tui\Render\RenderContext(20, 4));
        $screen = new \Symfony\Component\Tui\Terminal\ScreenBuffer(20, 4);
        $screen->write(implode("\r\n", $lines));
        $cells = $screen->getCells();
        $this->assertSame('┌', $cells[0][0]['char']);
        $this->assertNotSame($cells[0][0]['style'], $cells[0][7]['style']);
        $this->assertSame('T', $cells[1][1]['char']);
        $this->assertStringContainsString('38;2;0;255;0', $cells[1][1]['style']);
    }

    public function testEncodedBorderImagesReuseTheExistingRasterAndSvgConsumers()
    {
        if (!\function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('The GD extension is needed for terminal images.');
        }
        $image = imagecreatetruecolor(1, 1);
        imagesetpixel($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        $sources = ['image/png' => $png, 'image/svg+xml' => '<svg xmlns="http://www.w3.org/2000/svg" width="8" height="6"><rect width="8" height="6" fill="#ff0000"/></svg>'];
        foreach ($sources as $mime => $data) {
            $source = 'data:'.$mime.';base64,'.base64_encode($data);
            $session = (new \Symfony\Component\HtmlRenderer\HtmlRenderer())->createSession('<div style="width:8ch;height:3lh;border:1px solid;border-image-source:url('.$source.');color:#00ff00">Text</div>', new \Symfony\Component\HtmlRenderer\RenderingContext(160, 64));
            $lines = (new \Symfony\Bridge\TuiHtml\Widget\DocumentWidget($session))->render(new \Symfony\Component\Tui\Render\RenderContext(20, 4));
            $screen = new \Symfony\Component\Tui\Terminal\ScreenBuffer(20, 4);
            $screen->write(implode("\r\n", $lines));
            $cells = $screen->getCells();
            $this->assertStringContainsString('38;2;255;0;0', $cells[0][0]['style'], $mime);
            $this->assertSame('T', $cells[1][1]['char']);
            $this->assertStringContainsString('38;2;0;255;0', $cells[1][1]['style']);
        }
    }

    public function testScriptsReuseTheRetainedUnicodeMappingAndWholeRunFallback()
    {
        $list = new DisplayList([
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => '2', 'color' => 'default', 'vertical-align' => 'super'],
            ['type' => 'text', 'x' => 8.0, 'y' => 0.0, 'text' => '2', 'color' => 'default', 'vertical-align' => 'sub'],
            ['type' => 'text', 'x' => 16.0, 'y' => 0.0, 'text' => 'q2', 'color' => 'default', 'vertical-align' => 'super'],
        ]);
        $line = (new DisplayListRenderer())->render($list, 10, 1)[0];
        $this->assertSame('²₂q2', rtrim(AnsiUtils::stripAnsiCodes($line)));
    }

    public function testUnderlineVariantsAndColorKeepTheGlyphFillIndependent()
    {
        $list = new DisplayList([
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'Text', 'color' => '#00ff00', 'text-decoration-line' => 'underline', 'text-decoration-style' => 'wavy', 'text-decoration-color' => 'red', 'text-decoration-thickness' => 3.5],
        ]);
        $line = (new DisplayListRenderer())->render($list, 10, 1)[0];
        $this->assertStringContainsString('4:3', $line);
        $this->assertStringContainsString('58;5;1', $line);
        $this->assertStringContainsString('38;2;0;255;0', $line);
        $this->assertSame('Text', rtrim(AnsiUtils::stripAnsiCodes($line)));
    }

    public function testMeasuresUnicodeWithTerminalMetrics()
    {
        $metrics = (new TerminalTextMeasurer())->measure("A中e\u{0301}", new FontStyle());
        $this->assertSame(32.0, $metrics->width);
        $this->assertSame(16.0, $metrics->ascent + $metrics->descent);
    }

    public function testQuantizesAndClipsTextAtCellBoundaries()
    {
        $list = new DisplayList([
            ['type' => 'push-clip', 'x' => 8.0, 'y' => 0.0, 'width' => 16.0, 'height' => 16.0],
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'abcd', 'color' => '#ff0000'],
            ['type' => 'pop-clip'],
        ]);
        $lines = (new DisplayListRenderer())->render($list, 5, 1);
        $this->assertSame(' bc  ', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertStringContainsString('38;2;255;0;0', $lines[0]);
    }

    public function testClippingThroughAWideGraphemeKeepsTheFollowingTextInPlace()
    {
        $list = new DisplayList([
            ['type' => 'push-clip', 'x' => 8.0, 'y' => 0.0, 'width' => 16.0, 'height' => 16.0],
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => '中x', 'color' => 'black'],
            ['type' => 'pop-clip'],
        ]);
        $lines = (new DisplayListRenderer())->render($list, 5, 1);
        $this->assertSame('  x  ', AnsiUtils::stripAnsiCodes($lines[0]));
    }

    public function testRendersImageOperationsWithTheExistingTerminalImageRenderer()
    {
        if (!\extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is required.');
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="8" fill="red"/><rect y="8" width="16" height="8" fill="blue"/></svg>';
        $list = new DisplayList([['type' => 'svg', 'x' => 0.0, 'y' => 0.0, 'width' => 16.0, 'height' => 16.0, 'data' => $svg, 'mime-type' => 'image/svg+xml']]);
        $lines = (new DisplayListRenderer())->render($list, 4, 1);
        $this->assertStringContainsString('▀', $lines[0]);
        $this->assertStringContainsString('38;2;255;0;0', $lines[0]);
        $this->assertStringContainsString('48;2;0;0;255', $lines[0]);
        $this->assertSame(4, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testResolvedEllipticalRadiiUseExistingTerminalCornerGlyphs()
    {
        $list = new DisplayList([['type' => 'border', 'x' => 0.0, 'y' => 0.0, 'width' => 32.0, 'height' => 48.0, 'color' => 'black', 'thickness' => 1.0, 'style' => 'solid', 'radii' => [[8.0, 16.0], [0.0, 4.0], [2.0, 2.0], [3.0, 0.0]]]]);
        $lines = (new DisplayListRenderer())->render($list, 4, 3);
        $this->assertSame(['╭──┐', '│  │', '└──╯'], array_map(AnsiUtils::stripAnsiCodes(...), $lines));
    }

    public function testReusesTerminalBorderPatternsAndPerSideColors()
    {
        $list = new DisplayList([['type' => 'border', 'x' => 0.0, 'y' => 0.0, 'width' => 32.0, 'height' => 48.0, 'color' => 'black', 'sides' => [
            'top' => ['thickness' => 1.0, 'color' => '#ff0000', 'style' => 'solid'],
            'right' => ['thickness' => 0.0, 'color' => 'black', 'style' => 'none'],
            'bottom' => ['thickness' => 1.0, 'color' => '#008000', 'style' => 'dotted'],
            'left' => ['thickness' => 1.0, 'color' => '#0000ff', 'style' => 'solid'],
        ]]]);
        $lines = (new DisplayListRenderer())->render($list, 4, 3);
        $this->assertSame(['┌───', '│   ', '└┈┈┈'], array_map(AnsiUtils::stripAnsiCodes(...), $lines));
        $this->assertStringContainsString('38;2;255;0;0', $lines[0]);
        $this->assertStringContainsString('38;2;0;0;255', $lines[1]);
        $this->assertStringContainsString('38;2;0;128;0', $lines[2]);
    }

    public function testATransparentTopBorderDoesNotHideTheOtherSides()
    {
        $list = new DisplayList([['type' => 'border', 'x' => 0.0, 'y' => 0.0, 'width' => 32.0, 'height' => 48.0, 'color' => 'transparent', 'sides' => [
            'top' => ['thickness' => 1.0, 'color' => 'transparent', 'style' => 'solid'],
            'right' => ['thickness' => 1.0, 'color' => '#ff0000', 'style' => 'solid'],
            'bottom' => ['thickness' => 0.0, 'color' => 'transparent', 'style' => 'none'],
            'left' => ['thickness' => 0.0, 'color' => 'transparent', 'style' => 'none'],
        ]]]);
        $lines = (new DisplayListRenderer())->render($list, 4, 3);
        $this->assertStringContainsString('│', $lines[1]);
        $this->assertStringContainsString('38;2;255;0;0', $lines[1]);
    }

    public function testPreservesTextRunEmphasisAndDecorations()
    {
        $list = new DisplayList([['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'Text', 'color' => 'default', 'font-weight' => 700, 'font-style' => 'italic', 'text-decoration-line' => 'underline line-through']]);
        $lines = (new DisplayListRenderer())->render($list, 4, 1);
        $this->assertSame('Text', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertMatchesRegularExpression('/\e\[(?:0;)?1;3;4;9m/', $lines[0]);
    }

    public function testClipsOnlyTheAxesRequestedByTheDisplayList()
    {
        $list = new DisplayList([
            ['type' => 'push-clip', 'x' => 8.0, 'y' => 16.0, 'width' => 8.0, 'height' => 16.0, 'clip-x' => true, 'clip-y' => false],
            ['type' => 'text', 'x' => 8.0, 'y' => 0.0, 'text' => 'abc', 'color' => 'default'],
            ['type' => 'pop-clip'],
        ]);
        $lines = (new DisplayListRenderer())->render($list, 4, 2);
        $this->assertSame(' a  ', AnsiUtils::stripAnsiCodes($lines[0]));
    }

    public function testReusesTerminalBidiOrderingAndArabicShaping()
    {
        if (!Bidi::isAvailable()) {
            $this->markTestSkipped('Terminal bidi processing is unavailable.');
        }
        $list = new DisplayList([
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'שלום 123', 'color' => 'default', 'direction' => 'rtl'],
            ['type' => 'text', 'x' => 0.0, 'y' => 16.0, 'text' => 'سلام', 'color' => 'default', 'direction' => 'rtl'],
            ['type' => 'text', 'x' => 0.0, 'y' => 32.0, 'text' => "A中e\u{0301}", 'color' => 'default'],
        ]);
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), (new DisplayListRenderer())->render($list, 8, 3));
        $this->assertSame('123 םולש', $lines[0]);
        $this->assertSame('ﻡﺎﻠﺳ', rtrim($lines[1]));
        $this->assertSame("A中e\u{0301}", rtrim($lines[2]));
        $this->assertSame(8, AnsiUtils::visibleWidth($lines[2]));
    }

    public function testAdaptsColorCapabilitiesWhileKeepingTextAttributes()
    {
        $list = new DisplayList([['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'a', 'color' => '#123abc', 'font-weight' => 700]]);
        $limited = (new DisplayListRenderer(new TerminalTextMeasurer(), (new MediaContext())->withColors(16)))->render($list, 1, 1)[0];
        $this->assertSame('a', AnsiUtils::stripAnsiCodes($limited));
        $this->assertStringNotContainsString('38;2;', $limited);
        $forced = (new DisplayListRenderer(new TerminalTextMeasurer(), (new MediaContext())->withForcedColors(true)))->render($list, 1, 1)[0];
        $this->assertSame('a', AnsiUtils::stripAnsiCodes($forced));
        $this->assertStringNotContainsString('38;2;', $forced);
        $this->assertMatchesRegularExpression('/\e\[(?:0;)?1m/', $forced);
    }

    public function testComposesFractionalTranslationsBeforeCellQuantization()
    {
        $list = new DisplayList([
            ['type' => 'push-transform', 'matrix' => [1.0, 0.0, 0.0, 1.0, 8.5, 16.25]],
            ['type' => 'push-transform', 'matrix' => [1.0, 0.0, 0.0, 1.0, 7.5, -0.25]],
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'x', 'color' => 'default'],
            ['type' => 'pop-transform'],
            ['type' => 'text', 'x' => 0.0, 'y' => 16.0, 'text' => 'y', 'color' => 'default'],
            ['type' => 'pop-transform'],
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'z', 'color' => 'default'],
        ]);
        $lines = (new DisplayListRenderer())->render($list, 4, 3);
        $this->assertSame(['z   ', '  x ', ' y  '], array_map(AnsiUtils::stripAnsiCodes(...), $lines));
    }

    public function testUnsupportedRotationDoesNotBecomeAnInventedTranslation()
    {
        $list = new DisplayList([
            ['type' => 'push-transform', 'matrix' => [0.0, 1.0, -1.0, 0.0, 20.0, -20.0]],
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'x', 'color' => 'default'],
            ['type' => 'pop-transform'],
        ]);
        $this->assertSame('x   ', AnsiUtils::stripAnsiCodes((new DisplayListRenderer())->render($list, 4, 1)[0]));
    }

    public function testConsumesResolvedGradientModelsAndCompositesTheirAlpha()
    {
        $model = ['kind' => 'linear', 'repeating' => false, 'from' => 0.0, 'center' => [8.0, 8.0], 'direction' => [1.0, 0.0], 'radii' => [0.0, 0.0], 'length' => 16.0, 'stops' => [[[[1.0, 0.0, 0.0], 1.0], 0.0], [[[0.0, 0.0, 1.0], 1.0], 1.0]], 'space' => 'srgb', 'hue' => 'shorter'];
        $list = new DisplayList([['type' => 'gradient', 'x' => 0.0, 'y' => 0.0, 'width' => 16.0, 'height' => 16.0, 'gradient' => $model]]);
        $lines = (new DisplayListRenderer())->render($list, 2, 1);
        $this->assertStringContainsString('48;2;191;0;64', $lines[0]);
        $this->assertStringContainsString('48;2;64;0;191', $lines[0]);
        $model['stops'] = [[[[0.0, 0.0, 1.0], 0.5], 0.0], [[[0.0, 0.0, 1.0], 0.5], 1.0]];
        $list = new DisplayList([
            ['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => 16.0, 'height' => 16.0, 'color' => '#ff0000'],
            ['type' => 'gradient', 'x' => 0.0, 'y' => 0.0, 'width' => 16.0, 'height' => 16.0, 'gradient' => $model],
        ]);
        $this->assertStringContainsString('48;2;128;0;128', (new DisplayListRenderer())->render($list, 2, 1)[0]);
    }

    public function testCaretUsesTheNativeCursorMarkerWithoutOverwritingGraphemes()
    {
        $list = new DisplayList([
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => 'A中B', 'color' => 'default'],
            ['type' => 'caret', 'x' => 24.0, 'y' => 0.0, 'width' => 1.0, 'height' => 16.0, 'color' => 'default'],
        ]);
        $line = (new DisplayListRenderer())->render($list, 5, 1)[0];
        $this->assertStringContainsString(AnsiUtils::cursorMarker(), $line);
        [$before, $after] = explode(AnsiUtils::cursorMarker(), $line, 2);
        $this->assertSame('A中', AnsiUtils::stripAnsiCodes($before));
        $this->assertSame('B ', AnsiUtils::stripAnsiCodes($after));
        $this->assertSame(5, AnsiUtils::visibleWidth($line));
    }

    public function testDocumentTextCannotInjectTerminalCommands()
    {
        $list = new DisplayList([['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'text' => "Safe\x1b]0;hostile\x07", 'color' => 'black']]);
        $lines = (new DisplayListRenderer())->render($list, 10, 1);
        $this->assertSame('Safe      ', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertStringNotContainsString('hostile', $lines[0]);
    }
}
