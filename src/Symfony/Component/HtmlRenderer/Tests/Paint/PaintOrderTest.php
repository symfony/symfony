<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class PaintOrderTest extends TestCase
{
    public function testPositionedDescendantEscapesStaticAndAutoZIndexAncestors()
    {
        foreach ([[], ['position' => 'relative', 'z-index' => 'auto']] as $parentStyle) {
            [$document, $front, $uncle, $fragment] = $this->overlap($parentStyle);
            $list = (new Painter())->paint($fragment);
            self::assertSame(['Uncle', 'Front'], array_column($list->getOperations(), 'text'));
            self::assertSame($front, (new RenderingResult($document, new \SplObjectStorage(), $fragment, $list))->hitTest(5, 5));
        }
    }

    public function testOpacityIsolationAndTransformsContainDescendantStackLevels()
    {
        foreach ([['opacity' => '.5'], ['isolation' => 'isolate'], ['transform' => 'translate(0px)'], ['position' => 'relative', 'z-index' => '1']] as $parentStyle) {
            [$document, $front, $uncle, $fragment] = $this->overlap($parentStyle);
            $list = (new Painter())->paint($fragment);
            self::assertSame(['Front', 'Uncle'], array_column($list->getOperations(), 'text'));
            self::assertSame($uncle, (new RenderingResult($document, new \SplObjectStorage(), $fragment, $list))->hitTest(5, 5));
        }
    }

    public function testNegativeDescendantIsAboveRootBackgroundButBelowNonContextFlow()
    {
        $negative = new Fragment(null, new Rect(0, 0, 20, 20), ['position' => 'absolute', 'z-index' => '-1', 'background-color' => 'red'], 'Negative');
        $flow = new Fragment(null, new Rect(0, 0, 20, 20), ['background-color' => 'blue'], 'Flow', [$negative]);
        $root = new Fragment(null, new Rect(0, 0, 20, 20), ['background-color' => 'white'], null, [$flow]);
        $operations = (new Painter())->paint($root)->getOperations();
        self::assertSame(['rectangle', 'rectangle', 'text', 'rectangle', 'text'], array_column($operations, 'type'));
        self::assertSame(['white', 'red', 'black', 'blue', 'black'], array_column($operations, 'color'));
        self::assertSame(['Negative', 'Flow'], array_column($operations, 'text'));
    }

    public function testNegativeLayerInsideContextIsBetweenItsBackgroundAndContent()
    {
        $negative = new Fragment(null, new Rect(0, 0, 20, 20), ['position' => 'absolute', 'z-index' => '-1'], 'Negative');
        $context = new Fragment(null, new Rect(0, 0, 20, 20), ['opacity' => '.5', 'background-color' => 'blue'], 'Flow', [$negative]);
        $root = new Fragment(null, new Rect(0, 0, 20, 20), [], null, [$context]);
        $operations = (new Painter())->paint($root)->getOperations();
        self::assertSame(['push-opacity', 'rectangle', 'text', 'text', 'pop-opacity'], array_column($operations, 'type'));
        self::assertSame(['Negative', 'Flow'], array_column($operations, 'text'));
    }

    public function testEscapedLayerPreservesAncestorClipAndHitTestingOrder()
    {
        [$document, $front, $uncle, $fragment] = $this->overlap(['overflow' => 'hidden'], new Rect(0, 0, 10, 10));
        $list = (new Painter())->paint($fragment);
        $operations = $list->getOperations();
        self::assertSame(['Uncle', 'Front'], array_column($operations, 'text'));
        self::assertSame(['push-clip', 'text', 'pop-clip'], array_column(\array_slice($operations, -3), 'type'));
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, $list);
        self::assertSame($front, $result->hitTest(5, 5));
        self::assertSame($uncle, $result->hitTest(15, 5));
        self::assertSame($operations, (new Painter())->paint($fragment)->getOperations());
    }

    private function overlap(array $parentStyle, ?Rect $parentBounds = null): array
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><section id="parent"><button id="front">Front</button></section><button id="uncle">Uncle</button></main>');
        $front = $document->querySelector('#front');
        $uncle = $document->querySelector('#uncle');
        $parent = new Fragment($document->querySelector('#parent'), $parentBounds ?? new Rect(0, 0, 20, 20), $parentStyle, null, [new Fragment($front, new Rect(0, 0, 20, 20), ['position' => 'absolute', 'z-index' => '100'], 'Front')]);
        $fragment = new Fragment($document->querySelector('main'), new Rect(0, 0, 20, 20), [], null, [$parent, new Fragment($uncle, new Rect(0, 0, 20, 20), ['position' => 'absolute', 'z-index' => '2'], 'Uncle')]);

        return [$document, $front, $uncle, $fragment];
    }
}
