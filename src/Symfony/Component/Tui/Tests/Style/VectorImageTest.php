<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\VectorImage;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\SvgWidget;

class VectorImageTest extends TestCase
{
    public function testPathCommands()
    {
        // Absolute and relative lines, then a close that ends the subpath at its start
        $this->assertSame([['points' => [[10.0, 10.0], [20.0, 10.0], [20.0, 20.0], [10.0, 20.0]], 'closed' => true]], VectorImage::path('M10 10 H20 v10 h-10 z'));
        // Pairs after a moveto are linetos; a second moveto starts another subpath
        $this->assertSame([[[0.0, 0.0], [5.0, 5.0], [10.0, 0.0]], [[11.0, 1.0], [12.0, 2.0]]], array_column(VectorImage::path('M0,0 5,5 10,0 m1 1 l1 1'), 'points'));
        // Numbers without separators, like 1-2 and .5.5
        $this->assertSame([[0.0, 0.0], [1.0, -2.0], [1.5, -1.5]], VectorImage::path('M0 0L1-2l.5.5')[0]['points']);
        // An error ends the path, keeping what is before it
        $this->assertSame([[0.0, 0.0], [5.0, 0.0]], VectorImage::path('M0 0 L5 0 L oops')[0]['points']);
        $this->assertSame([], VectorImage::path('L5 5'), 'A path starts with a moveto.');
    }

    public function testCurvesAndArcsEndAtTheirEndPoints()
    {
        foreach (['M0 0 C 0 10 10 10 10 0', 'M0 0 Q 5 10 10 0', 'M0 0 Q 5 10 5 5 T 10 0', 'M0 0 c0 10 10 10 10 0', 'M0 0 A 5 5 0 0 1 10 0', 'M0 0 a5 5 0 0110 0'] as $data) {
            $points = VectorImage::path($data)[0]['points'];
            $this->assertEqualsWithDelta([10.0, 0.0], end($points), 1e-9, $data);
            $this->assertGreaterThan(3, \count($points), $data);
        }
        // A half circle from (0, 0) to (10, 0) passes 5 away from its center
        $points = VectorImage::path('M0 0 A 5 5 0 0 1 10 0')[0]['points'];
        $middle = $points[intdiv(\count($points), 2)];
        $this->assertEqualsWithDelta(5.0, hypot($middle[0] - 5, $middle[1]), 0.01);
        $this->assertLessThan(0, min(array_column($points, 1)), 'Clockwise from the left goes up, the y axis pointing down.');
        $this->assertSame([[0.0, 0.0], [10.0, 0.0]], VectorImage::path('M0 0 A 1e-200 1e-200 0 0 1 10 0')[0]['points'], 'Radii too small to square: a line.');
    }

    public function testTransforms()
    {
        $this->assertSame([1.0, 0.0, 0.0, 1.0, 5.0, 0.0], VectorImage::transform('translate(5)'));
        $this->assertSame([2.0, 0.0, 0.0, 3.0, 5.0, 0.0], VectorImage::transform('translate(5) scale(2, 3)'));
        $this->assertSame([2.0, 0.0, 0.0, 3.0, 10.0, 0.0], VectorImage::transform('scale(2, 3) translate(5)'));
        $this->assertEqualsWithDelta([0.0, 1.0, -1.0, 0.0, 10.0, 0.0], VectorImage::transform('rotate(90 5 5)'), 1e-9);
        $this->assertSame([1.0, 0.0, 0.0, 1.0, 0.0, 0.0], VectorImage::transform('scale()'), 'Invalid: none.');
    }

    public function testLabelsAreTheTextsNothingCovers()
    {
        $document = \Dom\HTMLDocument::createFromString('<svg viewBox="0 0 320 64" width="320"><text x="8" y="32">Secret</text><rect y="16" width="100" height="24"/><text x="200" y="32" text-anchor="middle" dominant-baseline="central" fill="red">Shown</text><defs><clipPath id="c"><rect width="50" height="64"/></clipPath></defs><text x="8" y="60" clip-path="url(#c)">Clipped</text></svg>', \LIBXML_NOERROR);
        $labels = VectorImage::fromElement($document->querySelector('svg'))->labels();

        $this->assertSame(['Shown'], array_column($labels, 'text'), 'Not a text a later shape fills over, nor a clipped one.');
        $this->assertEqualsWithDelta(200 / 320, $labels[0]['x'], 1e-9);
        $this->assertEqualsWithDelta(0.5, $labels[0]['y'], 1e-9, 'Its middle at its point, of dominant-baseline: central.');
        $this->assertSame([255, 0, 0], $labels[0]['color']);
    }

    #[RequiresPhpExtension('gd')]
    public function testLabelsFollowTheBoxTheImageIsDrawnIn()
    {
        // A box of another height draws other texts as characters: not from the pixels drawn for the first one
        $document = \Dom\HTMLDocument::createFromString('<svg viewBox="0 0 320 64" width="320"><text x="160" y="40" text-anchor="middle" fill="red">Label</text></svg>', \LIBXML_NOERROR);
        $widget = new SvgWidget(VectorImage::fromElement($document->querySelector('svg')));
        $widget->setBox(40, null, null);
        $this->assertStringContainsString('Label', AnsiUtils::stripAnsiCodes(implode("\n", $widget->render(new RenderContext(40, 20)))));
        $widget->setBox(40, 40, 'fill');
        $this->assertStringNotContainsString('Label', AnsiUtils::stripAnsiCodes(implode("\n", $lines = $widget->render(new RenderContext(40, 40)))));
        $this->assertStringContainsString('255;0;0', implode('', $lines), 'Drawn in the pixels.');
    }

    #[RequiresPhpExtension('gd')]
    public function testLabelsDrawNoControlCharacterOfTheDocument()
    {
        // The text of a label drawn as characters: an escape, a C1 control introducing a sequence, and a bell left out
        $html = "<svg viewBox=\"0 0 320 64\" width=\"320\"><rect width=\"320\" height=\"64\" fill=\"#123456\"/><text x=\"8\" y=\"32\" fill=\"white\">A\e[2J\u{9B}31mB\x07C</text></svg>";
        $terminal = new VirtualTerminal(60, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();

        $this->assertStringContainsString('A[2J31mBC', AnsiUtils::stripAnsiCodes($output), 'The text is drawn as characters.');
        $this->assertStringNotContainsString("\e[2J", $output);
        $this->assertStringNotContainsString("\u{9B}", $output);
        $this->assertStringNotContainsString("\x07C", $output);
    }

    #[RequiresPhpExtension('gd')]
    public function testDrawing()
    {
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 10 10" width="80"><rect width="5" height="10" fill="red"/><rect x="5" width="5" height="10" fill="currentColor" opacity=".5"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $image = VectorImage::fromElement($svg);
        $this->assertSame([80.0, 80.0], [$image->width, $image->height], 'The height follows the view box.');

        $png = imagecreatefromstring($image->toPng(10, 10, [0, 0, 255]));
        $this->assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], imagecolorsforindex($png, imagecolorat($png, 2, 5)));
        $right = imagecolorsforindex($png, imagecolorat($png, 8, 5));
        $this->assertSame([0, 0, 255], [$right['red'], $right['green'], $right['blue']]);
        $this->assertEqualsWithDelta(63, $right['alpha'], 2, 'Half transparent.');

        $this->assertNull(VectorImage::fromElement(\Dom\HTMLDocument::createFromString('<!doctype html><svg><desc>Hi</desc></svg>', \LIBXML_NOERROR)->querySelector('svg')), 'Nothing to draw.');
    }

    public function testLimits()
    {
        // At most that many points, the arcs and curves counted, the rest left out
        $points = array_sum(array_map(static fn (array $subpath) => \count($subpath['points']), VectorImage::path('M0 0'.str_repeat(' A 5 5 0 1 1 10 10 A 5 5 0 1 1 0 0', 1000), 300)));
        $this->assertLessThanOrEqual(300 + 128, $points);
        // Numbers past the largest length are that length, even infinite ones
        $this->assertSame([[0.0, 0.0], [100_000.0, -100_000.0]], VectorImage::path('M0 0 L1e400 -1e9')[0]['points']);
    }

    #[RequiresPhpExtension('gd')]
    public function testHugeSizesAndStrokesAreBounded()
    {
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg width="1e400" height="99999999" viewBox="0 0 1e400 1"><path d="M0 0 L1e400 1" stroke="red" stroke-width="1e300"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $image = VectorImage::fromElement($svg);
        $this->assertSame([100_000.0, 1.0], [$image->width, $image->height], 'A size past the largest one is none: the view box, as large as it can be, gives it.');
        $this->assertNotNull($image->toPng(100_000, 100_000, [0, 0, 0]));
        $this->assertNull($image->toPng(0, 10, [0, 0, 0]));

        // A size from extreme proportions of the view box is bounded too
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg width="160" viewBox="0 0 0.0001 24"><rect width="1" height="1"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $this->assertSame([160.0, 100_000.0], [VectorImage::fromElement($svg)->width, VectorImage::fromElement($svg)->height]);
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg width="160" viewBox="0 0 1 1e-308"><rect width="1" height="1"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $this->assertSame([160.0, 0.01], [VectorImage::fromElement($svg)->width, VectorImage::fromElement($svg)->height]);
        // Dots and corners of the widest strokes count as work too
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 10 10"><path d="M0 5 H10" stroke="red" stroke-width="1e9" stroke-dasharray="0 .003" stroke-linecap="round"/><polyline points="'.str_repeat('0,0 10,10 ', 2000).'" stroke="red" stroke-width="1e9" stroke-miterlimit="1e9"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $start = microtime(true);
        $this->assertNotNull(VectorImage::fromElement($svg)->toPng(160, 160, [0, 0, 0]));
        $this->assertLessThan(5, microtime(true) - $start);
    }

    #[RequiresPhpExtension('gd')]
    public function testTheStyleSheetsOfThePageStyleTheShapes()
    {
        $html = '<style>.icon rect { fill: red } .icon .b { fill: lime; stroke: none } svg .hidden { display: none } @media (min-width: 100000px) { .icon rect { fill: yellow } }</style>
            <svg class="icon" viewBox="0 0 30 10" width="240"><rect width="10" height="10" fill="blue"/><rect class="b" x="10" width="10" height="10" style="fill: navy"/><rect class="b hidden" x="20" width="10" height="10"/></svg>';
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();

        $this->assertStringContainsString('255;0;0', $output, 'A rule wins over the presentation attribute.');
        $this->assertStringContainsString('0;0;128', $output, 'The style attribute wins over the rules.');
        $this->assertStringNotContainsString('0;255;0', $output, 'display: none from a rule, of the shape that would be lime.');
        $this->assertStringNotContainsString('255;255;0', $output, 'Rules of media that do not match are left out.');
        $this->assertStringNotContainsString('0;0;255', $output);
    }

    #[RequiresPhpExtension('gd')]
    public function testTheStyleSheetsSetTheGeometryOfTheShapes()
    {
        $html = '<style>.c { cx: 25px; r: 5 } .p { d: path("M0 0 H5 V10 H0 Z") } .q { d: none } .w { width: auto; x: 50% }</style>
            <svg viewBox="0 0 30 10" width="240"><circle class="c" cx="5" cy="5" r="1" fill="#ff0000"/><path class="p" d="M10 0 H15 V10 H10 Z" fill="#00ff00"/><path class="q" d="M0 0 H30 V10 H0 Z" fill="#ffff00"/><rect class="w" width="2" height="10" fill="#0000ff"/></svg>';
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html>'.$html, \LIBXML_NOERROR)->querySelector('svg');
        $styleSheet = (new HtmlBuilder())->build($html)->getStyleSheet();
        $png = imagecreatefromstring(VectorImage::fromElement($svg, $styleSheet->svgDeclarations(...))->toPng(30, 10, [0, 0, 0]));
        $color = static fn (int $x, int $y) => imagecolorsforindex($png, imagecolorat($png, $x, $y));

        $this->assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $color(25, 5), 'Its center and its radius from the rule.');
        $this->assertSame(127, $color(5, 1)['alpha'], 'Not where its attributes put it.');
        $this->assertSame(['red' => 0, 'green' => 255, 'blue' => 0, 'alpha' => 0], $color(2, 8), 'The path of the rule.');
        $this->assertSame(127, $color(12, 2)['alpha'], 'Not that of the attribute; d: none draws nothing.');
        $this->assertSame(['red' => 0, 'green' => 0, 'blue' => 255, 'alpha' => 0], $color(15, 5), 'x from the rule; width: auto leaves the attribute.');

        $inline = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 10 10"><rect width="2" height="10" style="x: 6px; width: 3px" fill="#ff0000"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $png = imagecreatefromstring(VectorImage::fromElement($inline)->toPng(10, 10, [0, 0, 0]));
        $this->assertSame(0, imagecolorsforindex($png, imagecolorat($png, 7, 5))['alpha'], 'From the style attribute, without a style sheet.');
        $this->assertSame(127, imagecolorsforindex($png, imagecolorat($png, 1, 5))['alpha']);
    }

    #[RequiresPhpExtension('gd')]
    public function testTheShapesTakeTheCustomPropertiesOfWhatHoldsThem()
    {
        $html = '<style>:root { --accent: #ff0000; --line: 0 } .dark { --accent: #000080 } rect { fill: var(--accent) } .missing { fill: var(--nope, #00ff00) }</style>
            <svg viewBox="0 0 30 10" width="240"><rect width="10" height="10"/><g class="dark"><rect x="10" width="10" height="10"/></g><rect class="missing" x="20" width="10" height="10"/></svg>';
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();

        $this->assertStringContainsString('255;0;0', $output, 'From :root.');
        $this->assertStringContainsString('0;0;128', $output, 'Set again by a group around it.');
        $this->assertStringContainsString('0;255;0', $output, 'The fallback of a property set nowhere.');
    }

    #[RequiresPhpExtension('gd')]
    public function testTheShapesTakeTheCustomPropertiesTheyReadThroughOthersAndTheirStyleAttribute()
    {
        $html = '<style>:root { --brand: #ff0000; --accent: var(--brand); --unused: #123456 } body { --mark: #00ff00 } p { --unused: #654321 } rect { fill: var(--accent) }</style>
            <svg viewBox="0 0 20 10" width="160"><rect width="10" height="10"/><circle cx="15" cy="5" r="5" style="fill: var(--mark)"/></svg>';
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();

        $this->assertStringContainsString('255;0;0', $output, 'Through --accent, which reads --brand.');
        $this->assertStringContainsString('0;255;0', $output, 'Read by a style attribute.');
    }

    #[RequiresPhpExtension('gd')]
    public function testTheShapesTakeWhatTheRulesSetOnTheElementsAroundThem()
    {
        $css = '<style>.other { --tint: #0000ff } .theme { --tint: #ff0000 } rect { fill: var(--tint) }</style>';
        $html = '<body class="theme"><div><p class="other">x</p><svg viewBox="0 0 10 10" width="80"><rect width="10" height="10"/></svg></div></body>';
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html>'.$css.$html, \LIBXML_NOERROR)->querySelector('svg');
        $styleSheet = (new HtmlBuilder())->build($css)->getStyleSheet();
        $png = imagecreatefromstring(VectorImage::fromElement($svg, $styleSheet->svgDeclarations(...))->toPng(10, 10, [0, 0, 0]));

        $this->assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], imagecolorsforindex($png, imagecolorat($png, 5, 5)));
    }

    #[RequiresPhpExtension('gd')]
    public function testTheShapesAreStyledAgainInOtherMedia()
    {
        $html = '<style>rect { fill: #ff0000 } @media (prefers-color-scheme: light) { rect { fill: #000080 } }</style><svg viewBox="0 0 10 10" width="80"><rect width="10" height="10"/></svg>';
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(terminal: $terminal);
        $tui->setMediaContext($tui->getMediaContext()->withColorScheme(ColorScheme::Light));
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString('0;0;128', $terminal->consumeOutput(), 'In the media of the terminal, not of the build.');

        $tui->setMediaContext($tui->getMediaContext()->withColorScheme(ColorScheme::Dark));
        $tui->processRender();
        $this->assertStringContainsString('255;0;0', $terminal->consumeOutput(), 'Again when the color scheme changes.');
    }

    #[RequiresPhpExtension('gd')]
    public function testUseDrawsACopyOfTheElementItNames()
    {
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 10 10"><defs><symbol id="s"><rect width="5" height="10"/></symbol></defs><use href="#s" x="5" fill="lime"/><g id="loop"><use href="#loop"/></g><use href="#missing"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $png = imagecreatefromstring(VectorImage::fromElement($svg)->toPng(10, 10, [0, 0, 0]));
        $this->assertSame(127, imagecolorsforindex($png, imagecolorat($png, 2, 5))['alpha'], 'The symbol is not drawn itself.');
        $this->assertSame(['red' => 0, 'green' => 255, 'blue' => 0, 'alpha' => 0], imagecolorsforindex($png, imagecolorat($png, 7, 5)), 'Moved by x, in the fill of the <use>.');
    }

    public function testCopiesOfCopiesAreBounded()
    {
        // Each level ten copies of the one below: 10^6 rects, if nothing stopped them
        $svg = '<svg viewBox="0 0 10 10"><defs><g id="l0"><rect width="1" height="1"/></g>';
        for ($i = 1; $i <= 6; ++$i) {
            $svg .= '<g id="l'.$i.'">'.str_repeat('<use href="#l'.($i - 1).'"/>', 10).'</g>';
        }
        $image = VectorImage::fromElement(\Dom\HTMLDocument::createFromString('<!doctype html>'.$svg.'</defs><use href="#l6"/></svg>', \LIBXML_NOERROR)->querySelector('svg'));

        $this->assertNotNull($image);
        $this->assertLessThanOrEqual(2_000, \count((new \ReflectionProperty($image, 'shapes'))->getValue($image)));
    }

    #[RequiresPhpExtension('gd')]
    public function testGradients()
    {
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 10 10"><defs><linearGradient id="g"><stop offset="0" stop-color="#000"/><stop offset="1" stop-color="#fff"/></linearGradient><radialGradient id="r" href="#g"/></defs><rect width="10" height="5" fill="url(#g)"/><rect y="5" width="10" height="5" fill="url(#r)"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $png = imagecreatefromstring(VectorImage::fromElement($svg)->toPng(40, 40, [0, 0, 0]));
        $red = static fn (int $x, int $y): int => imagecolorsforindex($png, imagecolorat($png, $x, $y))['red'];

        // Left to right, across the bounding box of the shape
        $this->assertLessThan(30, $red(1, 10));
        $this->assertEqualsWithDelta(128, $red(20, 10), 12);
        $this->assertGreaterThan(225, $red(38, 10));
        // From the center out, its stops those of the gradient it refers to
        $this->assertLessThan(30, $red(20, 30));
        $this->assertGreaterThan($red(20, 30) + 60, $red(2, 30));
    }

    #[RequiresPhpExtension('gd')]
    public function testTheStopsAndTheTextTakeTheStyleSheets()
    {
        $styleSheet = CssStyleSheet::fromString('stop { stop-color: #00f } .faded { stop-opacity: 0 } g.label { font-size: 10px; text-anchor: middle }');
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 40 20"><defs><linearGradient id="sky"><stop offset="0"/><stop class="faded" offset="1"/></linearGradient></defs><rect width="40" height="4" fill="url(#sky)"/><g class="label" fill="#f00"><text x="20" y="15">Hi</text></g></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $png = imagecreatefromstring(VectorImage::fromElement($svg, $styleSheet->svgDeclarations(...))->toPng(80, 40, [0, 0, 0]));

        $start = imagecolorsforindex($png, imagecolorat($png, 1, 2));
        $this->assertSame([0, 0, 255], [$start['red'], $start['green'], $start['blue']], 'The color of the stops from a rule.');
        $this->assertGreaterThan(100, imagecolorsforindex($png, imagecolorat($png, 78, 2))['alpha'], 'Their opacity too: the end fades out.');

        // The text of the group: centered at x, as tall as its size
        $red = [];
        for ($x = 0; $x < 80; ++$x) {
            for ($y = 8; $y < 40; ++$y) {
                $pixel = imagecolorsforindex($png, imagecolorat($png, $x, $y));
                if ($pixel['alpha'] < 64 && $pixel['red'] > 128) {
                    $red[] = $x;
                }
            }
        }
        $this->assertNotEmpty($red);
        $this->assertGreaterThan(25, min($red), 'Anchored in the middle, inherited from the group.');
        $this->assertLessThan(55, max($red));
    }

    #[RequiresPhpExtension('gd')]
    public function testText()
    {
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 40 20"><text x="20" y="15" font-size="10" text-anchor="middle" fill="#f00">Hi</text></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $png = imagecreatefromstring(VectorImage::fromElement($svg)->toPng(80, 40, [0, 0, 0]));

        // Red pixels around the middle, between its top and its baseline; none at the sides
        $red = [];
        for ($x = 0; $x < 80; ++$x) {
            for ($y = 0; $y < 40; ++$y) {
                $pixel = imagecolorsforindex($png, imagecolorat($png, $x, $y));
                if ($pixel['alpha'] < 64 && $pixel['red'] > 128) {
                    $red[] = [$x, $y];
                }
            }
        }
        $this->assertNotEmpty($red);
        $this->assertGreaterThan(25, min(array_column($red, 0)));
        $this->assertLessThan(55, max(array_column($red, 0)));
        $this->assertLessThanOrEqual(32, max(array_column($red, 1)));

        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 40 20"><text x="0" y="15" transform="scale(1e300) scale(1e300)" fill="#f00">Hi</text><rect width="2" height="2"/></svg>', \LIBXML_NOERROR)->querySelector('svg');
        $this->assertNotNull(VectorImage::fromElement($svg)->toPng(80, 40, [0, 0, 0]), 'Infinitely large: not drawn.');
    }

    #[RequiresPhpExtension('gd')]
    public function testDashes()
    {
        // A line 40 long, dashes of 10 and gaps of 10: red in the first and third quarters only
        $row = $this->row('<line x1="0" y1="5" x2="40" y2="5" stroke="red" stroke-width="4" stroke-dasharray="10"/>', 40, 10, 5);
        $this->assertSame('xxxx....xxxx....', $this->sample($row, 16));

        // Shifted by the offset, a negative one too, and from its style
        $this->assertSame('..xxxx....xxxx..', $this->sample($this->row('<line x1="0" y1="5" x2="40" y2="5" stroke="red" stroke-width="4" stroke-dasharray="10 10" stroke-dashoffset="-5"/>', 40, 10, 5), 16));
        $this->assertSame('xx....xxxx....xx', $this->sample($this->row('<line x1="0" y1="5" x2="40" y2="5" stroke="red" stroke-width="4" style="stroke-dasharray: 10, 10; stroke-dashoffset: 5"/>', 40, 10, 5), 16));

        // Invalid or adding up to 0: solid
        foreach (['0 0', '10 -5', 'oops'] as $invalid) {
            $this->assertSame(str_repeat('x', 16), $this->sample($this->row('<line x1="0" y1="5" x2="40" y2="5" stroke="red" stroke-width="4" stroke-dasharray="'.$invalid.'"/>', 40, 10, 5), 16), $invalid);
        }
        // Too many dashes to draw: solid
        $this->assertSame(str_repeat('x', 16), $this->sample($this->row('<line x1="0" y1="5" x2="40" y2="5" stroke="red" stroke-width="4" stroke-dasharray=".001"/>', 40, 10, 5), 16));
        // Around a closed shape, past its last corner
        $png = $this->png('<rect x="5" y="5" width="30" height="30" fill="none" stroke="red" stroke-width="2" stroke-dasharray="5"/>', 40, 40);
        $this->assertTrue($this->red($png, 6, 5), 'A dash at the start.');
        $this->assertFalse($this->red($png, 13, 5), 'A gap.');
        $this->assertTrue($this->red($png, 5, 32), 'Up the last side.');
    }

    #[RequiresPhpExtension('gd')]
    public function testCapsAndJoins()
    {
        // butt ends at the end points, square and round go on by half the width
        $line = '<line x1="10" y1="10" x2="30" y2="10" stroke="red" stroke-width="8" stroke-linecap="%s"/>';
        $this->assertSame('.xxxxxxxxxx.', substr($this->sample($this->row(\sprintf($line, 'butt'), 40, 20, 10), 20), 4, 12));
        $this->assertSame('xxxxxxxxxxxx', substr($this->sample($this->row(\sprintf($line, 'square'), 40, 20, 10), 20), 4, 12));
        $this->assertSame('xxxxxxxxxxxx', substr($this->sample($this->row(\sprintf($line, 'round'), 40, 20, 10), 20), 4, 12));
        $this->assertFalse($this->red($this->png(\sprintf($line, 'round'), 40, 20), 7, 6), 'Round: not the corners of the square.');
        $this->assertTrue($this->red($this->png(\sprintf($line, 'square'), 40, 20), 7, 6));

        // A dash of no length is a dot with round caps
        $this->assertTrue($this->red($this->png('<line x1="0" y1="10" x2="40" y2="10" stroke="red" stroke-width="6" stroke-dasharray="0 20" stroke-linecap="round"/>', 40, 20), 20, 10));

        // The outer corner of a right angle: filled by miter, cut by bevel, round between them
        $corner = '<polyline points="10,30 10,10 30,10" fill="none" stroke="red" stroke-width="8" stroke-linejoin="%s"/>';
        $this->assertTrue($this->red($this->png(\sprintf($corner, 'miter'), 40, 40), 7, 7));
        $this->assertFalse($this->red($this->png(\sprintf($corner, 'bevel'), 40, 40), 7, 7));
        $this->assertFalse($this->red($this->png(\sprintf($corner, 'round'), 40, 40), 6, 6));
        $this->assertTrue($this->red($this->png(\sprintf($corner, 'round'), 40, 40), 8, 8));
        // A miter longer than the limit is bevelled: a right angle's is 1.41 widths
        $this->assertFalse($this->red($this->png('<polyline points="10,30 10,10 30,10" fill="none" stroke="red" stroke-width="8" stroke-miterlimit="1.2"/>', 40, 40), 7, 7));
        $this->assertTrue($this->red($this->png('<polyline points="10,30 10,10 30,10" fill="none" stroke="red" stroke-width="8" style="stroke-miterlimit: 1.5"/>', 40, 40), 7, 7));
    }

    public function testMarkersGoAtTheEndsOfTheCommands()
    {
        // Each command ends at a vertex, however many points its curve is drawn with; a close ends where it starts
        VectorImage::path('M0 0 L10 0 Q 15 5 10 10 Z M20 20 H30', vertices: $vertices);
        $points = VectorImage::path('M0 0 L10 0 Q 15 5 10 10 Z M20 20 H30');
        $this->assertSame([0, 1], \array_slice($vertices[0], 0, 2));
        $this->assertSame([10.0, 10.0], $points[0]['points'][end($vertices[0])]);
        $this->assertGreaterThan(4, end($vertices[0]));
        $this->assertSame([[0, 1]], \array_slice($vertices, 1));
    }

    #[RequiresPhpExtension('gd')]
    public function testMarkers()
    {
        $defs = '<defs><marker id="dot" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="2" markerHeight="2"><circle cx="5" cy="5" r="5" fill="lime"/></marker>'
            .'<marker id="arrow" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse" markerUnits="userSpaceOnUse"><path d="M0 0 L10 5 L0 10 Z" fill="lime"/></marker></defs>';
        // Dots of two widths of the stroke at the start, the middle and the end
        $png = $this->png($defs.'<polyline points="5,20 20,20 35,20" fill="none" stroke="red" stroke-width="2" marker-start="url(#dot)" marker-mid="url(#dot)" marker-end="url(#dot)"/>', 40, 40);
        foreach ([5, 20, 35] as $x) {
            $this->assertTrue($this->lime($png, $x, 21), "At $x.");
        }
        $this->assertFalse($this->lime($png, 12, 21), 'Not between the vertices.');

        // Arrows along the direction of the line at its end, and reversed at its start, from its style
        $png = $this->png($defs.'<line style="marker: url(#arrow)" x1="10" y1="20" x2="30" y2="20" stroke="red"/>', 40, 40);
        $this->assertTrue($this->lime($png, 28, 20), 'The arrow ends at the end, to its left.');
        $this->assertFalse($this->lime($png, 32, 20));
        $this->assertTrue($this->lime($png, 12, 20), 'At the start, pointing back.');
        $this->assertFalse($this->lime($png, 8, 20));
        // A longhand of the element wins over the shorthand
        $png = $this->png($defs.'<line style="marker: url(#arrow)" x1="10" y1="20" x2="30" y2="20" stroke="red" marker-start="none"/>', 40, 40);
        $this->assertFalse($this->lime($png, 12, 20));
        $this->assertTrue($this->lime($png, 28, 20));

        // An angle turns it, rects take none, and a marker that is not one draws nothing
        $png = $this->png(str_replace('auto-start-reverse', '90deg', $defs).'<line x1="20" y1="10" x2="20" y2="30" stroke="red" marker-end="url(#arrow)"/><rect width="5" height="5" fill="none" stroke="red" marker-start="url(#dot)"/><line x1="0" y1="39" x2="40" y2="39" stroke="red" marker-end="url(#nothing)"/>', 40, 40);
        $this->assertTrue($this->lime($png, 20, 28));
        $this->assertFalse($this->lime($png, 0, 0));
    }

    #[RequiresPhpExtension('gd')]
    public function testPaintOrder()
    {
        // A thick stroke drawn first leaves the fill over its inner half
        $shape = '<rect x="10" y="10" width="20" height="20" fill="lime" stroke="red" stroke-width="8" paint-order="%s"/>';
        $this->assertFalse($this->lime($this->png(\sprintf($shape, 'normal'), 40, 40), 12, 20));
        $this->assertTrue($this->lime($this->png(\sprintf($shape, 'stroke'), 40, 40), 12, 20));
        $this->assertTrue($this->lime($this->png(\sprintf($shape, 'markers stroke fill'), 40, 40), 12, 20));
        $this->assertFalse($this->lime($this->png(\sprintf($shape, 'stroke stroke'), 40, 40), 12, 20), 'Invalid: normal.');
        $this->assertTrue($this->red($this->png(\sprintf($shape, 'stroke'), 40, 40), 7, 20));

        // Markers under the stroke
        $marker = '<defs><marker id="m" markerWidth="10" markerHeight="10" refX="5" refY="5" markerUnits="userSpaceOnUse"><rect width="10" height="10" fill="lime"/></marker></defs><line x1="5" y1="20" x2="35" y2="20" stroke="red" stroke-width="4" marker-start="url(#m)" paint-order="%s"/>';
        $this->assertFalse($this->lime($this->png(\sprintf($marker, 'markers'), 40, 40), 6, 20));
        // The shapes of a marker take no markers, after one of another paint-order too
        $nested = '<defs><marker id="n" markerUnits="userSpaceOnUse" markerWidth="6" markerHeight="6" refX="3" refY="3"><rect width="6" height="6" fill="lime"/></marker>'
            .'<marker id="m" markerUnits="userSpaceOnUse" markerWidth="20" markerHeight="20"><path d="M0 0 H1" stroke="red" paint-order="stroke"/><path d="M0 0 H10" stroke="red" marker-end="url(#n)"/></marker></defs>'
            .'<line x1="10" y1="20" x2="30" y2="20" stroke="red" marker-start="url(#m)"/>';
        $this->assertFalse($this->lime($this->png($nested, 40, 40), 20, 20));

        // Points at the same place and markers on every vertex take a bounded time
        $deep = str_repeat('<g>', 200).'<marker id="d" markerUnits="userSpaceOnUse"><rect width="1" height="1" fill="lime"/></marker>'.str_repeat('</g>', 200);
        $start = microtime(true);
        $this->png('<defs>'.$deep.'</defs><polyline points="'.str_repeat('5,5 ', 1999).'6,6" stroke="red" marker="url(#d)"/><polyline points="'.str_repeat('5,5 6,6 ', 999).'" stroke="red" marker="url(#d)"/>', 40, 40);
        $this->assertLessThan(2, microtime(true) - $start);
        $this->assertTrue($this->lime($this->png(\sprintf($marker, 'normal'), 40, 40), 6, 20));
    }

    private function lime(\GdImage $png, int $x, int $y): bool
    {
        $pixel = imagecolorsforindex($png, imagecolorat($png, $x, $y));

        return $pixel['alpha'] < 64 && $pixel['green'] > 128 && $pixel['red'] < 128;
    }

    #[RequiresPhpExtension('gd')]
    public function testClipPaths()
    {
        // A square clipped to a circle: its corners left out
        $defs = '<defs><clipPath id="round"><circle cx="20" cy="20" r="10"/></clipPath>'
            .'<clipPath id="half" clipPathUnits="objectBoundingBox"><rect width=".5" height="1"/></clipPath>'
            .'<clipPath id="ring" clip-rule="evenodd"><path d="M5 5 H35 V35 H5 Z M15 15 H25 V25 H15 Z"/></clipPath>'
            .'<clipPath id="none"/></defs>';
        $png = $this->png($defs.'<rect x="10" y="10" width="20" height="20" fill="red" clip-path="url(#round)"/>', 40, 40);
        $this->assertTrue($this->red($png, 20, 20));
        $this->assertTrue($this->red($png, 20, 11));
        $this->assertFalse($this->red($png, 11, 11), 'A corner, outside the circle.');

        // In fractions of the box of the shape: its left half
        $png = $this->png($defs.'<rect x="10" y="10" width="20" height="20" fill="red" style="clip-path: url(#half)"/>', 40, 40);
        $this->assertTrue($this->red($png, 15, 20));
        $this->assertFalse($this->red($png, 25, 20));

        // evenodd leaves a hole; a group is clipped with what it holds, its transform too
        $png = $this->png($defs.'<g clip-path="url(#ring)"><rect width="40" height="40" fill="red"/></g>', 40, 40);
        $this->assertTrue($this->red($png, 8, 20));
        $this->assertFalse($this->red($png, 20, 20), 'The hole.');
        $this->assertFalse($this->red($png, 2, 2), 'Outside.');
        $png = $this->png($defs.'<g transform="translate(10 0)" clip-path="url(#round)"><rect width="40" height="40" fill="red"/></g>', 40, 40);
        $this->assertTrue($this->red($png, 30, 20), 'The circle moves with the group.');
        $this->assertFalse($this->red($png, 15, 20));

        // An empty clip path leaves nothing, a missing one is no clip
        $this->assertFalse($this->red($this->png($defs.'<rect width="40" height="40" fill="red" clip-path="url(#none)"/>', 40, 40), 20, 20));
        $this->assertTrue($this->red($this->png($defs.'<rect width="40" height="40" fill="red" clip-path="url(#missing)"/>', 40, 40), 2, 2));
        // Shapes after a clipped one are drawn as they are
        $png = $this->png($defs.'<rect width="40" height="40" fill="red" clip-path="url(#round)"/><rect width="5" height="5" fill="lime"/>', 40, 40);
        $this->assertTrue($this->lime($png, 2, 2));
        $this->assertFalse($this->red($png, 38, 38));
    }

    #[RequiresPhpExtension('gd')]
    public function testManyClippedShapesTakeABoundedTime()
    {
        $start = microtime(true);
        $this->png('<defs><clipPath id="c"><rect width="40" height="40"/><clipPath id="d"><use href="#c"/></clipPath></clipPath></defs>'.str_repeat('<rect width="40" height="40" fill="red" clip-path="url(#c)"/>', 1000), 40, 40);
        $this->assertLessThan(3, microtime(true) - $start);
        // A wide stroke with a long miter, clipped: its box no larger than the canvas
        $this->assertTrue($this->red($this->png('<defs><clipPath id="c"><rect width="40" height="40"/></clipPath></defs><polyline points="0,0 20,20 40,0" fill="none" stroke="red" stroke-width="1e300" stroke-miterlimit="1e308" clip-path="url(#c)"/>', 40, 40), 20, 30));
    }

    #[RequiresPhpExtension('gd')]
    public function testPatterns()
    {
        // Squares of 10, a red one of 5 in the corner of each: red where the tiles start, transparent between
        $checks = '<defs><pattern id="checks" width="10" height="10" patternUnits="userSpaceOnUse"><rect width="5" height="5" fill="red"/></pattern></defs>';
        $png = $this->png($checks.'<rect width="40" height="40" fill="url(#checks)"/>', 40, 40);
        $this->assertTrue($this->red($png, 2, 2));
        $this->assertTrue($this->red($png, 12, 22));
        $this->assertFalse($this->red($png, 7, 2));
        $this->assertFalse($this->red($png, 2, 7));

        // From x and y: the tiles start 5 further
        $png = $this->png(str_replace('patternUnits', 'x="5" y="5" patternUnits', $checks).'<rect width="40" height="40" fill="url(#checks)"/>', 40, 40);
        $this->assertFalse($this->red($png, 2, 2));
        $this->assertTrue($this->red($png, 7, 7));

        // Of the box of the shape by default: a quarter of it each way, its content in user units
        $png = $this->png('<defs><pattern id="q" width=".5" height=".5"><rect width="5" height="5" fill="red"/></pattern></defs><rect x="20" y="20" width="20" height="20" fill="url(#q)"/>', 40, 40);
        $this->assertTrue($this->red($png, 22, 22));
        $this->assertTrue($this->red($png, 32, 32));
        $this->assertFalse($this->red($png, 27, 27));
        $this->assertFalse($this->red($png, 2, 2), 'Only the shape is filled.');

        // In a viewBox, from the href of another pattern, as a stroke, with its opacity
        $png = $this->png('<defs><pattern id="base" viewBox="0 0 1 1" width="10" height="10" patternUnits="userSpaceOnUse"><rect width="1" height="1" fill="lime"/></pattern><pattern id="copy" href="#base"/></defs><rect width="40" height="20" fill="url(#copy)"/><line x1="0" y1="30" x2="40" y2="30" stroke="url(#base)" stroke-width="4"/>', 40, 40);
        $this->assertTrue($this->lime($png, 5, 5));
        $this->assertTrue($this->lime($png, 20, 30));

        // Turned, or without content, or naming itself: the fallback color, else nothing
        $this->assertTrue($this->red($this->png('<defs><pattern id="t" width="10" height="10" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="5" height="5" fill="lime"/></pattern></defs><rect width="40" height="40" fill="url(#t) red"/>', 40, 40), 7, 2));
        $this->assertFalse($this->red($this->png('<defs><pattern id="e" width="10" height="10"/></defs><rect width="40" height="40" fill="url(#e) red"/>', 40, 40), 2, 2));
        $png = $this->png('<defs><pattern id="s" width="10" height="10" patternUnits="userSpaceOnUse"><rect width="10" height="10" fill="url(#s) red"/></pattern></defs><rect width="40" height="40" fill="url(#s)"/>', 40, 40);
        $this->assertTrue($this->red($png, 2, 2), 'A pattern in itself is its fallback color.');
        $this->assertTrue($this->red($this->png('<defs><pattern id="h" width="10" height="10" patternUnits="userSpaceOnUse" patternTransform="scale(1e300) scale(1e300)"><rect width="5" height="5" fill="lime"/></pattern></defs><rect width="40" height="40" fill="url(#h) red"/>', 40, 40), 2, 2), 'Infinitely large: its fallback color.');

        // Patterns in patterns, with large tiles and an opacity, on many shapes: bounded
        $nested = '<defs><pattern id="a" width="40" height="40" patternUnits="userSpaceOnUse" patternTransform="scale(4)"><rect width="40" height="40" fill="url(#b)" fill-opacity=".5"/></pattern><pattern id="b" width="40" height="40" patternUnits="userSpaceOnUse" patternTransform="scale(4)"><rect width="40" height="40" fill="url(#c)" fill-opacity=".5"/></pattern><pattern id="c" width="40" height="40" patternUnits="userSpaceOnUse"><rect width="40" height="40" fill="red"/></pattern></defs>';
        $start = microtime(true);
        $this->png($nested.str_repeat('<rect width="40" height="40" fill="url(#a)" fill-opacity=".5"/>', 500), 40, 40);
        $this->assertLessThan(3, microtime(true) - $start);
    }

    #[RequiresPhpExtension('gd')]
    public function testMasks()
    {
        $alpha = static fn (\GdImage $png, int $x, int $y): int => imagecolorsforindex($png, imagecolorat($png, $x, $y))['alpha'];
        // White shows, black hides, gray halfway: by luminance
        $defs = '<defs><mask id="m" maskUnits="userSpaceOnUse" x="0" y="0" width="40" height="40"><rect width="20" height="40" fill="white"/><rect x="20" width="10" height="40" fill="#808080"/></mask>'
            .'<mask id="a" maskUnits="userSpaceOnUse" x="0" y="0" width="40" height="40" style="mask-type: alpha"><rect width="20" height="40" fill="black"/></mask>'
            .'<mask id="b" maskContentUnits="objectBoundingBox"><rect width=".5" height="1" fill="white"/></mask><mask id="none"/>'
            .'<mask id="self"><rect width="40" height="40" fill="white" mask="url(#self)"/></mask></defs>';
        $png = $this->png($defs.'<rect width="40" height="40" fill="red" mask="url(#m)"/>', 40, 40);
        $this->assertTrue($this->red($png, 5, 20));
        $this->assertSame(127, $alpha($png, 35, 20), 'Black hides.');
        $this->assertEqualsWithDelta(63, $alpha($png, 25, 20), 8, 'Gray: half.');

        // By alpha: black is opaque
        $png = $this->png($defs.'<rect width="40" height="40" fill="red" mask="url(#a)"/>', 40, 40);
        $this->assertTrue($this->red($png, 5, 20));
        $this->assertSame(127, $alpha($png, 35, 20));

        // In fractions of the box of the shape, its region around it by default
        $png = $this->png($defs.'<rect x="10" y="10" width="20" height="20" fill="red" mask="url(#b)"/>', 40, 40);
        $this->assertTrue($this->red($png, 15, 20));
        $this->assertSame(127, $alpha($png, 25, 20));

        // A group with what it holds; an empty mask hides, a missing one does nothing, one in itself is drawn once
        $png = $this->png($defs.'<g mask="url(#m)"><rect width="40" height="40" fill="red"/></g>', 40, 40);
        $this->assertTrue($this->red($png, 5, 20));
        $this->assertSame(127, $alpha($png, 35, 20));
        $this->assertSame(127, $alpha($this->png($defs.'<rect width="40" height="40" fill="red" mask="url(#none)"/>', 40, 40), 20, 20));
        $this->assertTrue($this->red($this->png($defs.'<rect width="40" height="40" fill="red" mask="url(#missing)"/>', 40, 40), 35, 20));
        $this->assertTrue($this->red($this->png($defs.'<rect x="10" y="10" width="20" height="20" fill="red" mask="url(#self)"/>', 40, 40), 20, 20));

        // Many masks, each a canvas: counted as work, a few kept at once
        $many = '';
        for ($i = 0; $i < 200; ++$i) {
            $many .= '<mask id="k'.$i.'" maskUnits="userSpaceOnUse" x="0" y="0" width="40" height="40"><rect width="40" height="40" fill="white"/></mask>';
        }
        $start = microtime(true);
        $this->assertNotNull($this->png('<defs>'.$many.'</defs>'.implode('', array_map(static fn (int $i) => '<rect width="40" height="40" fill="red" mask="url(#k'.$i.')"/>', range(0, 199))), 160, 160));
        $this->assertLessThan(3, microtime(true) - $start);
    }

    #[RequiresPhpExtension('gd')]
    public function testFilters()
    {
        $color = static function (\GdImage $png, int $x, int $y): array {
            $pixel = imagecolorsforindex($png, imagecolorat($png, $x, $y));

            return [$pixel['red'], $pixel['green'], $pixel['blue'], $pixel['alpha']];
        };
        // CSS functions: gray, then inverted
        $png = $this->png('<rect width="40" height="40" fill="red" style="filter: grayscale(1)"/>', 40, 40);
        [$r, $g, $b] = $color($png, 20, 20);
        $this->assertSame([$r, $r], [$g, $b], 'Gray.');
        $this->assertSame([0, 255, 255, 0], $color($this->png('<rect width="40" height="40" fill="red" filter="invert(1)"/>', 40, 40), 20, 20));

        // A drop shadow: the shape, its shadow moved below it on the right
        $png = $this->png('<rect x="5" y="5" width="20" height="20" fill="red" style="filter: drop-shadow(8px 8px 0 blue)"/>', 40, 40);
        $this->assertTrue($this->red($png, 10, 10));
        $this->assertSame([0, 0, 255, 0], $color($png, 30, 30), 'The shadow.');
        $this->assertSame(127, $color($png, 2, 35)[3]);

        // A <filter>: a color matrix, a flood composited in the shape, an offset merged under it, a blur
        $defs = '<defs><filter id="green"><feColorMatrix values="0 0 0 0 0  1 0 0 0 0  0 0 0 0 0  0 0 0 1 0"/></filter>'
            .'<filter id="flood"><feFlood flood-color="blue"/><feComposite in2="SourceGraphic" operator="in"/></filter>'
            .'<filter id="shadow" x="0" y="0" width="2" height="2"><feOffset in="SourceAlpha" dx="10" dy="10" result="moved"/><feMerge><feMergeNode in="moved"/><feMergeNode in="SourceGraphic"/></feMerge></filter>'
            .'<filter id="blur" x="-1" y="-1" width="3" height="3"><feGaussianBlur stdDeviation="4"/></filter>'
            .'<filter id="noise"><feTurbulence/></filter></defs>';
        $this->assertSame([0, 255, 0, 0], $color($this->png($defs.'<rect width="40" height="40" fill="red" filter="url(#green)"/>', 40, 40), 20, 20));
        $png = $this->png($defs.'<rect x="10" y="10" width="20" height="20" fill="red" filter="url(#flood)"/>', 40, 40);
        $this->assertSame([0, 0, 255, 0], $color($png, 20, 20), 'Blue in the shape...');
        $this->assertSame(127, $color($png, 5, 5)[3], '...not around it.');
        $png = $this->png($defs.'<rect x="5" y="5" width="15" height="15" fill="red" filter="url(#shadow)"/>', 40, 40);
        $this->assertTrue($this->red($png, 10, 10));
        $this->assertSame([0, 0, 0, 0], $color($png, 27, 27), 'The black of its alpha, moved.');
        $png = $this->png($defs.'<rect x="15" y="15" width="10" height="10" fill="red" filter="url(#blur)"/>', 40, 40);
        $this->assertGreaterThan(0, $color($png, 20, 20)[3], 'Blurred: no longer opaque...');
        $this->assertLessThan(127, $color($png, 12, 20)[3], '...and spread around it.');
        $this->assertTrue($this->red($this->png($defs.'<rect width="40" height="40" fill="red" filter="url(#noise)"/>', 40, 40), 20, 20), 'Another primitive: not applied.');

        // A merge of many inputs: at most 16, each counted
        $start = microtime(true);
        $merge = '<defs><filter id="many"><feMerge>'.str_repeat('<feMergeNode in="SourceAlpha"/>', 5000).'</feMerge></filter></defs>';
        $this->assertNotNull($this->png($merge.str_repeat('<rect width="40" height="40" fill="red" filter="url(#many)"/>', 50), 160, 160));
        $this->assertLessThan(3, microtime(true) - $start);
    }

    private function png(string $shapes, int $width, int $height): \GdImage
    {
        $svg = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 '.$width.' '.$height.'">'.$shapes.'</svg>', \LIBXML_NOERROR)->querySelector('svg');

        return imagecreatefromstring(VectorImage::fromElement($svg)->toPng($width, $height, [0, 0, 0]));
    }

    private function red(\GdImage $png, int $x, int $y): bool
    {
        $pixel = imagecolorsforindex($png, imagecolorat($png, $x, $y));

        return $pixel['alpha'] < 64 && $pixel['red'] > 128;
    }

    /**
     * @return list<bool>
     */
    private function row(string $shapes, int $width, int $height, int $y): array
    {
        $png = $this->png($shapes, $width, $height);

        return array_map(fn (int $x) => $this->red($png, $x, $y), range(0, $width - 1));
    }

    /**
     * @param list<bool> $row
     */
    private function sample(array $row, int $count): string
    {
        $step = \count($row) / $count;

        return implode('', array_map(static fn (int $i) => $row[(int) ($i * $step + $step / 2)] ? 'x' : '.', range(0, $count - 1)));
    }
}
