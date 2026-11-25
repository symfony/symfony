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
use Symfony\Component\HtmlRenderer\RenderingResult;

final class PickerLayoutTest extends TestCase
{
    public function testPickerFollowsScrolledTransformedOwnerAndEscapesClip()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><select><option>longer text</option><option>two</option></select></main>');
        $select = $document->querySelector('select');
        $options = $document->querySelectorAll('option');
        $picker = new Box($select, 'block', ['position' => 'fixed', 'left' => '0', 'top' => '0', 'width' => 'max-content', 'min-width' => '100%', 'max-height' => '50vh', 'overflow' => 'auto'], [
            new Box($options[0], 'block', [], [], 'longer text'), new Box($options[1], 'block', [], [], 'two'),
        ], null, null, null, 'picker', 1);
        $field = new Box($select, 'inline-block', ['width' => '40px', 'height' => '10px'], [$picker]);
        $root = new Box($document->querySelector('main'), 'block', ['width' => '100px', 'height' => '30px', 'font-size' => '10px', 'line-height' => '10px', 'overflow' => 'auto', '--html-scroll-y' => '20', 'transform' => 'translateX(10px)'], [new Box(null, 'block', ['height' => '40px']), $field]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        $bounds = $result->getBounds($select, 'picker')[0];
        self::assertSame(10.0, $bounds->x);
        self::assertSame(30.0, $bounds->y);
        self::assertSame(55.0, $bounds->width);
        self::assertSame($options[0], $result->hitTest(12, 32));
    }

    public function testPickerMinimumUsesVisualAnchorWidthAndClampsToViewport()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><select><option>A</option></select>');
        $select = $document->querySelector('select');
        $picker = new Box($select, 'block', ['position' => 'fixed', 'width' => 'max-content', 'min-width' => '100%', 'overflow' => 'auto'], [new Box($document->querySelector('option'), 'block', [], [], 'A')], null, null, null, 'picker', 1);
        $field = new Box($select, 'inline-block', ['width' => '40px', 'height' => '10px', 'transform' => 'scale(2)', 'transform-origin' => '0 0'], [$picker]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', ['padding-left' => '70px'], [$field]), 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        $bounds = $result->getBounds($select, 'picker')[0];
        self::assertSame(80.0, $bounds->width);
        self::assertSame(20.0, $bounds->x);
        self::assertSame(24.4, $bounds->y);
    }

    public function testGeneratedMinimumDoesNotMakePopupWiderThanViewport()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><select><option>A</option></select>');
        $select = $document->querySelector('select');
        $picker = new Box($select, 'block', ['position' => 'fixed', 'width' => 'max-content', 'min-width' => '100%'], [new Box($document->querySelector('option'), 'block', [], [], 'A')], null, null, null, 'picker', 1);
        $field = new Box($select, 'inline-block', ['width' => '200px', 'height' => '10px'], [$picker]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$field]), 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        $bounds = $result->getBounds($select, 'picker')[0];
        self::assertSame(100.0, $bounds->width);
        self::assertSame(0.0, $bounds->x);
    }
}
