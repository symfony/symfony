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
use Symfony\Component\HtmlRenderer\Paint\PaintOrder;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class TopLayerTest extends TestCase
{
    public function testTopLayerEscapesAncestorClipTransformAndHighSiblingStackingLevel()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><section><dialog></dialog></section><aside></aside></main>');
        $dialog = $document->querySelector('dialog');
        $box = new Box($document->querySelector('main'), 'block', ['width' => '100px'], [
            new Box($document->querySelector('section'), 'block', ['width' => '10px', 'height' => '10px', 'transform' => 'translateX(50px)', 'overflow' => 'hidden', 'opacity' => '.2'], [
                new Box($dialog, 'block', ['left' => '20%', 'top' => '20px', 'width' => '20px', 'height' => '20px', 'background-color' => 'red'], [], null, null, null, null, 1),
            ]),
            new Box($document->querySelector('aside'), 'block', ['position' => 'absolute', 'left' => '0', 'top' => '0', 'width' => '100px', 'height' => '100px', 'z-index' => '99999', 'background-color' => 'blue']),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        self::assertSame(10.0, $layout->bounds->height);
        self::assertSame(20.0, $result->getBounds($dialog)[0]->x);
        self::assertSame($dialog, $result->hitTest(25, 25));
        $steps = array_values(array_filter(PaintOrder::build($layout), static fn (array $step): bool => 'context-enter' === $step['type'] && $step['fragment']->node === $dialog));
        self::assertSame([], $steps[0]['clips']);
        self::assertSame([1.0, 0.0, 0.0, 1.0, 0.0, 0.0], $steps[0]['matrix']);
        $colors = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type']), 'color');
        self::assertSame(['blue', 'red'], $colors);
    }

    public function testNestedLayersUseActivationOrderAndBackdropPrecedesItsOwner()
    {
        $backdrop = new Box(null, 'block', ['left' => '0', 'top' => '0', 'width' => '100px', 'height' => '100px', 'background-color' => 'gray'], [], null, null, null, 'backdrop', 1);
        $nested = new Box(null, 'block', ['width' => '5px', 'height' => '5px', 'background-color' => 'green'], [], null, null, null, null, 3);
        $first = new Box(null, 'block', ['width' => '20px', 'height' => '20px', 'background-color' => 'red'], [$backdrop, $nested], null, null, null, null, 1);
        $second = new Box(null, 'block', ['width' => '20px', 'height' => '20px', 'background-color' => 'blue'], [], null, null, null, null, 2);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$first, $second]), 100, 100);
        $operations = (new Painter())->paint($layout)->getOperations();
        self::assertSame(['gray', 'red', 'blue', 'green'], array_column(array_filter($operations, static fn (array $operation): bool => 'rectangle' === $operation['type']), 'color'));
    }

    public function testOppositeInsetsAndAutomaticMarginsCenterDefinitePositionedBox()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'block', ['position' => 'absolute', 'left' => '0', 'right' => '0', 'top' => '0', 'bottom' => '0', 'width' => '20.5px', 'height' => '10.25px', 'margin' => 'auto'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(39.75, $layout->children[0]->bounds->x);
        self::assertSame(44.875, $layout->children[0]->bounds->y);
    }
}
