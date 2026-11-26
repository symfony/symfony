<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Content;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Content\CellDisplayList;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Paint\Painter;

class NativePopupSurfaceTest extends TestCase
{
    public function testOpaqueSurfaceClearsSpacesWithTheActualBackdrop()
    {
        foreach (['default', '#0000ee', '#124578'] as $background) {
            $native = new CellDisplayList()->convert([' X '], 3, 1, Color::hex('#eeeeee'), 'default' === $background ? Color::named('default') : Color::hex($background));
            $native = $this->surface($native, $background);
            new ContentLayout(16, $native);
            self::assertSame($background, $native->getOperations()[0]['color']);
            $lines = new DisplayListRenderer()->render(new DisplayList([
                ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'width' => 24.0, 'height' => 16.0, 'text' => 'ABC', 'color' => '#eeeeee'],
                ...$native->getOperations(),
            ]), 3, 1);
            $cells = TerminalCellSnapshot::normalize(implode("\n", $lines), 3, 1);
            self::assertSame('X', $cells[0][1][0]);
            self::assertSame(' ', $cells[0][0][0] ?? ' ');
            self::assertSame(' ', $cells[0][2][0] ?? ' ');
        }
    }

    public function testRealNativePopupClearsOnlyItsContentSurface()
    {
        foreach (['', 'body{background:blue}', 'body{background:blue}input[list]+select{background:#124578}', 'input[list]+select{padding-right:2ch}'] as $css) {
            $html = '<style>'.$css.'</style><label for="city">City</label><input id="city" list="cities" style="border:1px solid;max-width:24ch"><datalist id="cities"><option>Paris</option><option>Lyon</option><option>Lille</option></datalist><p>ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz</p>';
            $options = ['columns' => 40, 'rows' => 15, 'focus' => '#city', 'keys' => 'l'];
            $old = TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, false), 40, 15, [229, 229, 229]);
            $new = TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, true), 40, 15, [229, 229, 229]);
            // Compare the popup interior, including blank cells over the paragraph;
            // border background inheritance is a separate painting contract.
            for ($column = 6; $column < 14; ++$column) {
                self::assertSame($old[4][$column] ?? null, $new[4][$column] ?? null, $css.' column '.$column);
            }
            self::assertSame($old[4][20] ?? null, $new[4][20] ?? null);
        }
    }

    public function testReverseRetainsBackdropAndSurfaceRespectsClipping()
    {
        $native = new CellDisplayList()->convert(["\x1b[7mX\x1b[0m  "], 3, 1, Color::hex('#eeeeee'), Color::hex('#0000ee'));
        $native = $this->surface($native, '#0000ee');
        $lines = new DisplayListRenderer()->render(new DisplayList([
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'width' => 24.0, 'height' => 16.0, 'text' => 'ABC', 'color' => '#eeeeee'],
            ['type' => 'push-clip', 'x' => 0.0, 'y' => 0.0, 'width' => 16.0, 'height' => 16.0],
            ...$native->getOperations(),
            ['type' => 'pop-clip'],
        ]), 3, 1);
        $cells = TerminalCellSnapshot::normalize(implode("\n", $lines), 3, 1);
        self::assertSame(['X', [0, 0, 238], [238, 238, 238], ''], $cells[0][0]);
        self::assertSame(' ', $cells[0][1][0]);
        self::assertSame('C', $cells[0][2][0]);
    }

    public function testOrdinaryNativeContentStillPreservesUnpaintedCells()
    {
        $native = new CellDisplayList()->convert([' X '], 3, 1, Color::hex('#eeeeee'), Color::named('default'));
        self::assertNotContains('rectangle', array_column($native->getOperations(), 'type'));
        $lines = new DisplayListRenderer()->render(new DisplayList([
            ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'width' => 24.0, 'height' => 16.0, 'text' => 'ABC', 'color' => '#eeeeee'],
            ...$native->getOperations(),
        ]), 3, 1);
        self::assertSame('AXC', implode('', array_column(TerminalCellSnapshot::normalize(implode("\n", $lines), 3, 1)[0], 0)));
    }

    private function surface(DisplayList $native, string $background): DisplayList
    {
        return new Painter()->paint(new Fragment(null, new Rect(0, 0, 24, 16), [], null, [], null, null, [], null, null, null, null, null, null, new ContentLayout(16, $native, true, $background)));
    }
}
