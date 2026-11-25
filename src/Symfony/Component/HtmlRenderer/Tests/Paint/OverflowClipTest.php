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
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class OverflowClipTest extends TestCase
{
    public function testOverflowClipsChildrenAtPaddingEdgeWhileBorderRemainsPaintedAndHitTestable()
    {
        foreach (['hidden', 'clip'] as $overflow) {
            $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><div></div></main>');
            $owner = $document->querySelector('main');
            $child = $document->querySelector('div');
            $box = new Box($owner, 'block', ['width' => '20px', 'height' => '20px', 'border-width' => '5px', 'overflow' => $overflow], [new Box($child, 'block', ['width' => '30px', 'height' => '10px', 'margin-left' => '-10px', 'background-color' => 'red'])]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
            $operations = (new Painter())->paint($layout)->getOperations();
            $clips = array_values(array_filter($operations, static fn (array $op): bool => 'push-clip' === $op['type']));
            self::assertSame(5.0, $clips[0]['x']);
            self::assertSame(5.0, $clips[0]['y']);
            self::assertSame(20.0, $clips[0]['width']);
            self::assertSame('border', $operations[0]['type']);
            $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
            self::assertSame($owner, $result->hitTest(2, 6));
            self::assertSame($child, $result->hitTest(6,6));
        }
    }
}
