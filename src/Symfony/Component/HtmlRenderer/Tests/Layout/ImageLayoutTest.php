<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\Resource\ImageResource;

final class ImageLayoutTest extends TestCase
{
    public function testReplacedContentUsesIntrinsicRatioWithFractionalCssWidth()
    {
        $image = new ImageResource('bytes', 'image/png', 40.0, 20.0);
        $box = new Box(null, 'inline-block', ['width' => '10.25px'], [], null, $image);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(10.25, $fragment->bounds->width);
        self::assertSame(5.125, $fragment->bounds->height);
        $operation = (new Painter())->paint($fragment)->getOperations()[0];
        self::assertSame('image', $operation['type']);
        self::assertSame('bytes', $operation['data']);
        self::assertSame(10.25, $operation['width']);
        self::assertSame(5.125, $operation['height']);
    }

    public function testIntrinsicAndHeightOnlySizingDoNotFillViewport()
    {
        $image = new ImageResource('<svg/>', 'image/svg+xml', 40.0, 20.0);
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $natural = $engine->layout(new Box(null, 'inline-block', [], [], null, $image), 100, 100);
        self::assertSame(40.0, $natural->bounds->width);
        self::assertSame(20.0, $natural->bounds->height);
        $scaled = $engine->layout(new Box(null, 'inline-block', ['height' => '7.5px'], [], null, $image), 100, 100);
        self::assertSame(15.0, $scaled->bounds->width);
        self::assertSame(7.5, $scaled->bounds->height);
        self::assertSame('svg', (new Painter())->paint($scaled)->getOperations()[0]['type']);
    }

    public function testFlexIntrinsicContributionsHonorDefiniteFractionalHeightAndExplicitWidth()
    {
        $image = new ImageResource('bytes', 'image/png', 64.0, 32.0);
        foreach ([['height' => '7.25px'], [], ['height' => '7.25px', 'width' => '11.25px'], ['height' => '7.5px', 'aspect-ratio' => '4 / 1']] as $index => $style) {
            $box = new Box(null, 'flex', ['width' => '200px', 'align-items' => 'start', 'gap' => '8px'], [new Box(null, 'inline-block', $style, [], null, $image), new Box(null, 'inline', [], [], 'Title')]);
            $fragment = new LayoutEngine(new MonospaceTextMeasurer())->layout($box, 200, 100);
            $expected = [14.5, 64.0, 11.25, 30.0][$index];
            self::assertSame($expected, $fragment->children[0]->bounds->width);
            self::assertSame($expected + 8.0, $fragment->children[1]->bounds->x);
        }
    }

    public function testHeightBasedRatioUsesContentBoxChromeExactlyOnce()
    {
        $image = new ImageResource('bytes', 'image/png', 40.0, 20.0);
        $style = ['height' => '7.5px', 'box-sizing' => 'border-box', 'padding' => '1.25px', 'border-width' => '.5px'];
        $box = new Box(null, 'flex', ['width' => '200px', 'align-items' => 'start'], [new Box(null, 'inline-block', $style, [], null, $image)]);
        $fragment = new LayoutEngine(new MonospaceTextMeasurer())->layout($box, 200, 100)->children[0];
        self::assertSame(11.5, $fragment->bounds->width);
        self::assertSame(7.5, $fragment->bounds->height);
        self::assertSame(8.0, $fragment->children[0]->bounds->width);
        self::assertSame(4.0, $fragment->children[0]->bounds->height);
    }

    public function testExplicitAutoAndPreferredRatioPreserveReplacedSizingSemantics()
    {
        $image = new ImageResource('bytes', 'image/png', 40.0, 20.0);
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $fragment = $engine->layout(new Box(null, 'inline-block', ['width' => 'auto', 'height' => '7.5px'], [], null, $image), 200, 100);
        self::assertSame(15.0, $fragment->bounds->width);
        $fragment = $engine->layout(new Box(null, 'inline-block', ['width' => '20px', 'aspect-ratio' => '4'], [], null, $image), 200, 100);
        self::assertSame(5.0, $fragment->bounds->height);
        $fragment = $engine->layout(new Box(null, 'inline-block', ['height' => '7.5px', 'aspect-ratio' => 'auto 4'], [], null, $image), 200, 100);
        self::assertSame(15.0, $fragment->bounds->width, 'A loaded image uses its intrinsic ratio when the author also specifies auto.');
    }

    public function testReplacedBaselineUsesOuterBorderBoxInsteadOfItsPaintedImage()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><p style="margin:0;font-size:20px;line-height:20px"><img id="image" style="width:10px;height:10px;padding:2px;border:1px solid" src="data:image/svg+xml,'.rawurlencode($svg).'"><span id="text">A</span></p>');
        $result = new \Symfony\Component\HtmlRenderer\HtmlRenderer()->render($document, new \Symfony\Component\HtmlRenderer\RenderingContext(200, 100));
        self::assertSame(0.0, $result->getBounds($document->getElementById('image'))[0]->y);
        self::assertSame(0.0, $result->getBounds($document->getElementById('text'))[0]->y);
        $images = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'svg' === $op['type']));
        self::assertCount(1, $images);
        self::assertSame(3.0, $images[0]['y']);
    }

    public function testAuthorsCannotSpoofTheInternalReplacedBaselineFlag()
    {
        $html = '<!doctype html><p style="margin:0;font-size:20px;line-height:20px"><span style="display:inline-block;padding:2px;border:1px solid;EXTRA">A</span><b>B</b></p>';
        $renderer = new \Symfony\Component\HtmlRenderer\HtmlRenderer();
        $context = new \Symfony\Component\HtmlRenderer\RenderingContext(200, 100);
        self::assertSame($renderer->render(str_replace('EXTRA', '', $html), $context)->getDisplayList()->getOperations(), $renderer->render(str_replace('EXTRA', '-renderer-replaced:1', $html), $context)->getDisplayList()->getOperations());
    }

    public function testHeightConstraintsTransferThroughTheIntrinsicRatioBeforeFlexSizing()
    {
        $image = new ImageResource('bytes', 'image/png', 40.0, 20.0);
        foreach ([['height' => '15px', 'max-height' => '10px'], ['max-height' => '10px'], ['min-height' => '30px']] as $index => $style) {
            $box = new Box(null, 'flex', ['width' => '200px', 'align-items' => 'start'], [new Box(null, 'inline-block', $style, [], null, $image)]);
            $fragment = new LayoutEngine(new MonospaceTextMeasurer())->layout($box, 200, 100)->children[0];
            self::assertSame([20.0, 20.0, 60.0][$index], $fragment->bounds->width);
            self::assertSame([10.0, 10.0, 30.0][$index], $fragment->bounds->height);
        }
    }
}
