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
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

final class BackgroundBlendTest extends TestCase
{
    public function testBlendGroupDoesNotUseEarlierDocumentPaintAsItsBlendBackdrop()
    {
        $cells = $this->render('<div style="width:3ch;height:1;background:rgb(0,0,255);position:relative"><div style="position:absolute;inset:0;background:linear-gradient(rgb(255,0,0),rgb(255,0,0));background-blend-mode:multiply"></div></div>');
        self::assertStringContainsString('48;2;255;0;0', $cells[0][0]['style']);
        $cells = $this->render('<div style="width:3ch;height:1;background:linear-gradient(rgb(255,0,0),rgb(255,0,0)) rgb(0,0,255);background-blend-mode:screen"></div>');
        self::assertStringContainsString('48;2;255;0;255', $cells[0][0]['style']);
    }

    public function testMaskedTranslucentLayersComposeBeforeColoringTheGlyphs()
    {
        $cells = $this->render('<div style="width:3ch;height:1;background:linear-gradient(rgb(255 0 0 / .5),rgb(255 0 0 / .5)),linear-gradient(rgb(0,0,255),rgb(0,0,255));background-blend-mode:screen,normal;background-clip:text;-webkit-text-fill-color:transparent">ABC</div>');
        self::assertSame('A', $cells[0][0]['char']);
        self::assertStringContainsString('38;2;128;0;255', $cells[0][0]['style']);
        self::assertStringNotContainsString('48;', $cells[0][0]['style']);
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('gd')]
    public function testImagePixelsBlendInsideTranslatedClippedGroups()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="8" height="16"><rect width="8" height="16" fill="red"/></svg>';
        $uri = 'data:image/svg+xml,'.rawurlencode($svg);
        $cells = $this->render('<div style="width:2ch;height:1;overflow:hidden"><div style="width:3ch;height:1;transform:translateX(8px);background:rgb(0,0,255) url('.$uri.');background-blend-mode:screen"></div></div>');
        self::assertSame(' ', $cells[0][0]['char']);
        self::assertStringContainsString('38;2;255;0;255', $cells[0][1]['style']);
        self::assertSame(' ', $cells[0][2]['char']);
        self::assertSame('', $cells[0][2]['style']);
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('gd')]
    public function testGroupReservesAllVariantsBeforeAnyNativeImageDecode()
    {
        $image = imagecreatetruecolor(2048, 2048);
        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        unset($image);
        $cache = new \Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache(32, 16777216, 33554432);
        $layers = [];
        for ($i = 1; $i <= 5; ++$i) {
            $layers[] = ['type' => 'background-image', 'x' => 0.0, 'y' => 0.0, 'width' => 8.0, 'height' => 16.0, 'data' => $data, 'mime-type' => 'image/png', 'pattern' => ['x' => ['start' => 0.0, 'size' => 8.0 * $i, 'step' => 8.0 * $i, 'count' => 1], 'y' => ['start' => 0.0, 'size' => 16.0, 'step' => 16.0, 'count' => 1]], 'blend-mode' => 'multiply'];
        }
        $renderer = new DisplayListRenderer(new TerminalTextMeasurer(), new \Symfony\Bridge\TuiHtml\Style\MediaContext(), $cache);
        $group = ['type' => 'background-group', 'x' => 0.0, 'y' => 0.0, 'width' => 8.0, 'height' => 16.0, 'layers' => $layers];
        try {
            $renderer->render(new \Symfony\Component\HtmlRenderer\Paint\DisplayList([$group]), 1, 1);
            self::fail('Five separate four-megapixel variants must exceed the group budget.');
        } catch (\LengthException $exception) {
            self::assertStringContainsString('sampling budget', $exception->getMessage());
        }
        $entries = new \ReflectionProperty($cache, 'entries')->getValue($cache);
        self::assertCount(5, $entries);
        foreach ($entries as $entry) {
            self::assertNull(new \ReflectionProperty($entry['image'], 'image')->getValue($entry['image']), 'Reservation must precede every native decode.');
        }
        $group['layers'] = array_fill(0, 5, $layers[0]);
        self::assertNotEmpty($renderer->render(new \Symfony\Component\HtmlRenderer\Paint\DisplayList([$group]), 1, 1), 'Repeated use of the same object reserves it only once.');
    }

    private function render(string $html): array
    {
        $metrics = new TerminalTextMeasurer();
        $list = new HtmlRenderer()->render('<!doctype html>'.$html, new RenderingContext(80, 48, $metrics, null, new TerminalLayoutMetrics($metrics)))->getDisplayList();
        $screen = new ScreenBuffer(10, 3);
        $screen->write(implode("\r\n", new DisplayListRenderer()->render($list, 10, 3)));

        return $screen->getCells();
    }
}
