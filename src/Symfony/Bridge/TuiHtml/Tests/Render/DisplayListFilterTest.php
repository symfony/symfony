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
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

final class DisplayListFilterTest extends TestCase
{
    public function testFilterAppliesToDescendantsWithoutErasingOtherCells()
    {
        $list = new DisplayList([
            ['type' => 'rectangle', 'x' => 0, 'y' => 0, 'width' => 40, 'height' => 16, 'color' => '#123456'],
            ['type' => 'text', 'x' => 0, 'y' => 0, 'text' => 'ABCDE', 'color' => '#ffffff'],
            ['type' => 'push-filter', 'functions' => [['grayscale', 1.0]]],
            ['type' => 'text', 'x' => 8, 'y' => 0, 'text' => '界', 'color' => '#ff0000'],
            ['type' => 'push-filter', 'functions' => [['brightness', 0.5]]],
            ['type' => 'text', 'x' => 24, 'y' => 0, 'text' => 'D', 'color' => '#ffffff'],
            ['type' => 'pop-filter'],
            ['type' => 'pop-filter'],
        ]);
        $screen = new ScreenBuffer(5, 1);
        $screen->write(implode("\r\n", (new DisplayListRenderer())->render($list, 5, 1)));
        $cells = $screen->getCells()[0];
        self::assertSame('A界DE', rtrim($screen->getScreen()));
        self::assertStringContainsString('38;2;54;54;54', $cells[1]['style']);
        self::assertStringContainsString('38;2;128;128;128', $cells[3]['style']);
        self::assertStringContainsString('48;2;18;52;86', $cells[1]['style']);
        self::assertSame($cells[0]['style'], $cells[4]['style']);
    }

    public function testFullInvertKeepsTheExistingTerminalReverseApproximation()
    {
        $media = new MediaContext(7, 1, ColorScheme::Dark, Density::Regular, false, true);
        $list = new DisplayList([
            ['type' => 'push-filter', 'functions' => [['invert', 1.0]]],
            ['type' => 'text', 'x' => 0, 'y' => 0, 'text' => 'Swap', 'color' => 'red'],
            ['type' => 'text', 'x' => 40, 'y' => 0, 'text' => 'Y', 'color' => 'red'],
            ['type' => 'pop-filter'],
            ['type' => 'text', 'x' => 48, 'y' => 0, 'text' => 'X', 'color' => 'red'],
        ]);
        $screen = new ScreenBuffer(7, 1);
        $screen->write(implode("\r\n", (new DisplayListRenderer(new TerminalTextMeasurer(), $media))->render($list, 7, 1)));
        $cells = $screen->getCells()[0];
        self::assertStringContainsString('7', $cells[0]['style']);
        self::assertStringContainsString('7', $cells[4]['style']);
        self::assertStringNotContainsString('7', $cells[6]['style']);
    }

    public function testFilterRewritesScaledGlyphPayloadWithoutChangingItsReservation()
    {
        $media = new MediaContext(4, 2, ColorScheme::Dark, Density::Regular, false, false, false, null, false, MediaContext::TRUE_COLORS, true);
        $list = new DisplayList([
            ['type' => 'push-filter', 'functions' => [['grayscale', 1.0]]],
            ['type' => 'text', 'x' => 0, 'y' => 0, 'text' => 'A', 'color' => '#ff0000', 'font-size' => 32.0],
            ['type' => 'pop-filter'],
        ]);
        $lines = (new DisplayListRenderer(new TerminalTextMeasurer(), $media))->render($list, 4, 2);
        $payloads = iterator_to_array(\Symfony\Bridge\TuiHtml\Internal\TextMarkers::scaledPayloads($lines[0]));
        self::assertCount(1, $payloads);
        self::assertSame(0, $payloads[0][0]);
        self::assertSame(2, $payloads[0][2]);
        self::assertStringContainsString('38;2;54;54;54', $payloads[0][1]);
        self::assertStringNotContainsString('38;2;255;0;0', $payloads[0][1]);
    }
}
