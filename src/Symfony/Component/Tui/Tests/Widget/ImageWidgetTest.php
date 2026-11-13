<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\LinkEvent;
use Symfony\Component\Tui\Html\Element\ImageElement;
use Symfony\Component\Tui\Html\Element\PictureElement;
use Symfony\Component\Tui\Html\ElementRegistry;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\CellSize;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\ImageMapWidget;
use Symfony\Component\Tui\Widget\ImageWidget;
use Symfony\Component\Tui\Widget\RichTextWidget;

#[RequiresPhpExtension('gd')]
final class ImageWidgetTest extends TestCase
{
    public function testEachCellShowsTwoPixels()
    {
        $lines = (new ImageWidget(self::png(2, 2, static function (\GdImage $image) {
            imagesetpixel($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
            imagesetpixel($image, 0, 1, imagecolorallocate($image, 0, 0, 255));
        }), 'Pixels', 2, 1))->render(new RenderContext(10, 10));

        $this->assertCount(1, $lines);
        $this->assertSame('▀ ', AnsiUtils::stripAnsiCodes($lines[0]), 'The right column is transparent: nothing is drawn.');
        $this->assertStringContainsString("\e[38;2;255;0;0;48;2;0;0;255m▀", $lines[0], 'Red above blue.');
    }

    public function testTheSizeFollowsTheImageWithinTheRoom()
    {
        $image = self::png(32, 16);

        $this->assertSame([4, 1], self::measure(new ImageWidget($image), 40), '8 pixels per column, square pixels.');
        $this->assertSame([10, 3], self::measure(new ImageWidget($image, columns: 10), 40));
        $this->assertSame([12, 3], self::measure(new ImageWidget($image, rows: 3), 40), 'The width follows the height.');
        $this->assertSame([2, 1], self::measure(new ImageWidget($image, columns: 10), 2), 'Never wider than the room.');
        $this->assertSame([1, 1000], self::measure(new ImageWidget(self::png(1, 2, null), columns: 1, rows: 99999999), 40), 'At most 1000 rows, like the box.');
        $this->assertSame([1, 1000], self::measure(new ImageWidget(self::png(1, 4000), ''), 40));
    }

    public function testObjectPositionPlacesTheImageInItsBox()
    {
        // Red on the left, blue on the right: two columns
        $image = self::png(2, 2, static function (\GdImage $image) {
            imagefilledrectangle($image, 0, 0, 0, 1, imagecolorallocate($image, 255, 0, 0));
            imagefilledrectangle($image, 1, 0, 1, 1, imagecolorallocate($image, 0, 0, 255));
        });
        $render = static function (int $columns, ?array $position) use ($image): string {
            $widget = new ImageWidget($image, '', 2, 1);
            $widget->setBox($columns, 1, 'none', $position);

            return $widget->render(new RenderContext(10, 10))[0];
        };

        $this->assertSame('  ▀▀  ', AnsiUtils::stripAnsiCodes($render(6, null)), 'Centered by default.');
        $this->assertSame('▀▀    ', AnsiUtils::stripAnsiCodes($render(6, [0.0, 0.5])), 'left');
        $this->assertSame('    ▀▀', AnsiUtils::stripAnsiCodes($render(6, [1.0, 0.5])), 'right');
        $this->assertSame('      ', AnsiUtils::stripAnsiCodes($render(6, [-1e9, 0.5])), 'Far outside: nothing drawn, nothing more allocated.');
        $this->assertSame('      ', AnsiUtils::stripAnsiCodes($render(6, [1e9, 0.5])));
        // The color the cell is drawn with: the last one before it
        $drawn = static fn (string $line) => preg_match_all('/\e\[38;2;(\d+;\d+;\d+)/', strstr($line, '▀', true) ?: '', $m) ? end($m[1]) : null;
        $this->assertSame('255;0;0', $drawn($render(1, [0.0, 0.5])), 'Cut: the left part.');
        $this->assertSame('0;0;255', $drawn($render(1, [1.0, 0.5])), 'Cut: the right part.');
    }

    public function testWhatCannotBeDecodedShowsItsAlternative()
    {
        $broken = new ImageWidget('not an image', 'A chart');
        $this->assertFalse($broken->isDrawable());
        $this->assertSame(['[A chart]'], $broken->render(new RenderContext(40, 10)));
        $this->assertSame([], (new ImageWidget('', ''))->render(new RenderContext(40, 10)));
        $this->assertSame('A chart', $broken->getLabel());

        $huge = self::png(1, 1);
        $huge = substr_replace($huge, pack('NN', 100_000, 100_000), 16, 8);
        $this->assertFalse((new ImageWidget($huge))->isDrawable(), 'The size is checked before decoding.');
    }

    public function testPictureSourcesFollowTheMedia()
    {
        $images = ['dark.png' => self::png(8, 2), 'narrow.png' => self::png(16, 2), 'light.png' => self::png(24, 2)];
        $registry = (new ElementRegistry())->register('img', new ImageElement(static fn (string $src) => $images[$src] ?? throw new \RuntimeException('Not found.')));
        $view = (new HtmlBuilder(registry: $registry))->build('<picture id="pic">
            <source media="(prefers-color-scheme: dark)" srcset="dark.png 1x, dark@2x.png 2x">
            <source media="(max-width: 30)" srcset="narrow.png">
            <source media="screen { } p" srcset="dark.png">
            <source srcset="missing.png" type="image/avif">
            <img src="light.png" alt="Logo">
        </picture>');
        $picture = $view->getById('pic');
        $this->assertInstanceOf(ContainerWidget::class, $picture);
        $this->assertCount(4, $picture->all(), 'The two candidates of the first source (its 2x read when drawn), the second source, the one with braces and the missing one skipped, and the img.');

        $terminal = new VirtualTerminal(40, 6);
        $tui = new Tui(terminal: $terminal);
        $view->mount($tui);
        // The images are 1, 2 and 3 columns wide: the half blocks drawn say which one is shown
        $buffer = new ScreenBuffer(40, 6);
        $shown = static function () use ($tui, $terminal, $buffer): int {
            $tui->requestRender(true);
            $tui->processRender();
            $buffer->write($terminal->consumeOutput());

            return mb_substr_count($buffer->getScreen(), '▀');
        };

        $tui->setMediaContext(new MediaContext(colorScheme: ColorScheme::Dark));
        $tui->start();
        $this->assertSame(1, $shown(), 'Dark: the first source.');
        $tui->setMediaContext(new MediaContext(colorScheme: ColorScheme::Light));
        $this->assertSame(3, $shown(), 'Light and wide: the img.');
        $terminal->simulateResize(20, 6);
        $this->assertSame(2, $shown(), 'Narrow: the second source.');
        $tui->stop();
    }

    public function testTheSelectorsOfThePageDoNotSeeHowAPictureSwitchesItsSources()
    {
        $registry = (new ElementRegistry())->register('img', new ImageElement(static fn (string $src) => self::png(8, 2)));
        $view = (new HtmlBuilder(registry: $registry))->build('<style>img:not([class]) { padding-left: 4ch }</style><picture><source srcset="a.png"><img src="b.png" alt="B"></picture>');

        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(terminal: $terminal);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 4);
        $buffer->write($terminal->consumeOutput());
        $tui->stop();

        $this->assertSame(4, mb_strpos($buffer->getScreen(), '▀'), 'The source shown has no class, like the <img> it stands for.');
    }

    public function testTheCandidateOfASrcsetFollowsTheDensity()
    {
        $images = ['one.png' => self::png(8, 2), 'two.png' => self::png(16, 2), 'three.png' => self::png(24, 2)];
        $read = [];
        $registry = (new ElementRegistry())->register('img', new ImageElement(static function (string $src) use ($images, &$read) {
            $read[] = $src;

            return $images[$src] ?? throw new \RuntimeException('Not found.');
        }));
        $view = (new HtmlBuilder(registry: $registry))->build('<img id="x" src="one.png" srcset="three.png 3x, two.png 2x, one.png 1x" alt="X">');
        $this->assertSame(['one.png'], array_values(array_unique($read)), 'Built, only the first candidate is read: the others when they are drawn.');

        $terminal = new VirtualTerminal(40, 6);
        $tui = new Tui(terminal: $terminal);
        $view->mount($tui);
        $buffer = new ScreenBuffer(40, 6);
        // The images are 1, 2 and 3 columns wide: the half blocks drawn say which one is shown
        $shown = static function (int $cellHeight) use ($tui, $terminal, $buffer): int {
            $tui->setMediaContext(new MediaContext(cellSize: new CellSize(8, $cellHeight)));
            $tui->requestRender(true);
            $tui->processRender();
            $buffer->write($terminal->consumeOutput());

            return mb_substr_count($buffer->getScreen(), '▀');
        };
        $tui->start();
        $this->assertSame(1, $shown(16), '1dppx: the 1x candidate.');
        $this->assertNotContains('three.png', $read, 'Not drawn, not read.');
        $this->assertSame(2, $shown(32), '2dppx: the 2x one.');
        $this->assertSame(3, $shown(40), '2.5dppx: the smallest one at least as dense, 3x.');
        $this->assertSame(3, $shown(64), 'Denser than all: the densest.');
        $tui->stop();

        $this->assertSame([['(max-resolution: 1dppx)', 's.png'], ['', 'l.png']], PictureElement::srcsetCandidates('s.png 400w, l.png 800w', '(max-width: 30) 100px, 400px'), 'Width descriptors: densities of the px length of sizes.');
        $this->assertSame([['', 's.png']], PictureElement::srcsetCandidates('s.png 400w, l.png 800w'), 'Without a size, the first candidate.');
        $this->assertSame([['(max-resolution: 1dppx)', 'x.png'], ['', 'data:image/png;base64,AA,BB']], PictureElement::srcsetCandidates('data:image/png;base64,AA,BB 2x, x.png'), 'A URL holds commas.');
    }

    public function testImgElements()
    {
        $png = self::png(16, 16);
        $loaded = [];
        $registry = (new ElementRegistry())->register('img', new ImageElement(static function (string $src) use ($png, &$loaded) {
            $loaded[] = $src;

            return 'missing.png' === $src ? throw new \RuntimeException('Not found.') : $png;
        }));
        $view = (new HtmlBuilder(registry: $registry))->build('<img id="data" src="data:image/png;base64,'.base64_encode($png).'" alt="Data" width="80" height="48"><img id="file" src="logo.png" alt="Logo"><img id="missing" src="missing.png" alt="Gone"><img id="none" alt="No src">');

        $data = $view->getById('data');
        $this->assertInstanceOf(ImageWidget::class, $data);
        $this->assertSame([10, 3], self::measure($data, 40), 'width and height are CSS pixels.');
        $this->assertInstanceOf(ImageWidget::class, $view->getById('file'));
        $this->assertSame(['logo.png', 'missing.png'], $loaded, 'data: URLs need no loader, and each image is read once.');
        // The images drawn and the text alternatives, in a line
        $texts = array_map(static fn ($widget) => $widget->getText(), array_filter($data->getParent()?->all() ?? [], static fn ($widget) => $widget instanceof RichTextWidget));
        $this->assertSame(['[Gone][No src]'], array_values($texts), 'A loader that throws: the alt text, in the line of the text around it.');

        $this->assertSame('partial', $registry->getSupport()['html.elements.img']->level->value ?? null, 'Replacing the factory keeps the support note.');

        $default = (new HtmlBuilder())->build('<p><img id="file" src="logo.png" alt="Logo"></p>');
        $this->assertSame('[Logo]', $default->getBody()->all()[0]->getText(), 'Without a loader, no file is read.');
    }

    public function testTheAreasOfAnImageMapAreLinks()
    {
        $png = 'data:image/png;base64,'.base64_encode(self::png(32, 16));
        $view = (new HtmlBuilder())->build('<base href="https://example.com/"><img id="map" src="'.$png.'" alt="Map" width="32" height="16" usemap="#nav"><map name="nav"><area shape="rect" coords="0,0,16,16" href="#top" alt="Top"><area shape="circle" coords="24,8,4" href="docs" alt="Docs"><area href="" alt="Nothing"></map><img id="plain" src="'.$png.'" alt="Plain" width="32" height="16">');

        $image = $view->getById('map');
        $this->assertInstanceOf(ImageWidget::class, $image);
        $line = $image->render(new RenderContext(40, 5))[0];
        $this->assertMatchesRegularExpression('/^\e\]8;'.RichTextWidget::FRAGMENT_LINK.':id=tui-area-\d+-0;#top\x07(?:\e\[[0-9;]*m▀){2}\e\]8;;\x07/', $line, 'The cells of the rect: a link to the fragment.');
        $this->assertMatchesRegularExpression('/\e\]8;id=tui-area-\d+-1;https:\/\/example\.com\/docs\x07(?:\e\[[0-9;]*m▀){2}\e\]8;;\x07/', $line, 'The cells of the circle: a link to the URL, against the base.');
        $this->assertStringNotContainsString("\e]8;", $view->getById('plain')->render(new RenderContext(40, 5))[0], 'An image without usemap has no links.');
    }

    public function testAnImageMapTakesTheFocusAndFollowsItsAreas()
    {
        $png = 'data:image/png;base64,'.base64_encode(self::png(32, 16));
        $view = (new HtmlBuilder())->build('<img id="map" src="'.$png.'" alt="Map" width="32" height="16" usemap="#nav"><map name="nav"><area shape="rect" coords="0,0,16,16" href="#top" alt="Top"><area shape="rect" coords="16,0,32,16" href="https://example.com" alt="Out"></map>');
        $image = $view->getById('map');
        $this->assertInstanceOf(ImageMapWidget::class, $image);
        $followed = [];
        $image->on(LinkEvent::class, static function (LinkEvent $event) use (&$followed) { $followed[] = $event->getHref(); });

        $this->assertStringNotContainsString(AnsiUtils::CURSOR_MARKER_PREFIX, $image->render(new RenderContext(40, 5))[0]);
        $image->setFocused(true);
        $line = $image->render(new RenderContext(40, 5))[0];
        $this->assertSame('#top', $image->getCurrentHref());
        $this->assertLessThan(strpos($line, ';https://'), strpos($line, AnsiUtils::CURSOR_MARKER_PREFIX), 'The cursor on the first area.');
        $this->assertStringContainsString("\e[7m", $line, 'Reversed.');

        $image->handleInput("\t");
        $this->assertSame('https://example.com', $image->getCurrentHref(), 'Tab: the next area.');
        $image->handleInput("\r");
        $this->assertSame(['https://example.com'], $followed, 'Enter follows it.');
    }

    /**
     * @return array{int, int}
     */
    public function testOnlyTheCellsInTheBoxAreDrawn()
    {
        $quarters = self::png(2, 2, static function (\GdImage $image) {
            imagesetpixel($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
            imagesetpixel($image, 1, 0, imagecolorallocate($image, 0, 0, 255));
            imagesetpixel($image, 0, 1, imagecolorallocate($image, 0, 255, 0));
            imagesetpixel($image, 1, 1, imagecolorallocate($image, 255, 255, 255));
        });
        // Drawn in a million cells, of which the box shows two: the corner object-position says
        $widget = new ImageWidget($quarters, '', 1000, 1000);
        $widget->setBox(2, 1, 'none', [1.0, 1.0]);
        $lines = $widget->render(new RenderContext(10, 10));
        $this->assertSame('▀▀', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertStringContainsString('255;255;255', $lines[0], 'The bottom right corner.');
        $this->assertStringNotContainsString('255;0;0', $lines[0]);

        $widget->setBox(2, 1, 'none', [0.0, 0.0]);
        $this->assertStringContainsString('255;0;0', $widget->render(new RenderContext(10, 10))[0], 'The top left one.');

        $image = new RasterImage(self::png(5, 6, static function (\GdImage $image) {
            for ($i = 0; $i < 30; ++$i) {
                imagesetpixel($image, $i % 5, intdiv($i, 5), imagecolorallocate($image, $i * 8, 0, 0));
            }
        }));
        $all = $image->cells(5, 3);
        $this->assertSame(array_map(static fn (array $row) => \array_slice($row, 1, 3), \array_slice($all, 1, 2)), $image->cells(5, 3, [1, 1, 2, 3]), 'A window of the cells.');
        $this->assertSame([[$all[2][4]]], $image->cells(5, 3, [2, 4, 9, 9]), 'Cut at the edges.');
    }

    private static function measure(object $widget, int $room): array
    {
        $lines = $widget->render(new RenderContext($room, 50));

        return [AnsiUtils::visibleWidth($lines[0] ?? ''), \count($lines)];
    }

    private static function png(int $width, int $height, ?\Closure $draw = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        if (null === $draw) {
            imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 200, 0));
        } else {
            $draw($image);
        }
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
