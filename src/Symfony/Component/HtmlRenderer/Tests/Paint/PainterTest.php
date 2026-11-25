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

final class PainterTest extends TestCase
{
    public function testDisplayListContainsOrderedSerializableOperations()
    {
        $fragment = new Fragment(null, new Rect(0.25, 0.5, 30.5, 20.5), ['background-color' => '#fff', 'overflow' => 'hidden'], null, [new Fragment(null, new Rect(1.25, 2.5, 20.0, 12.0), ['color' => '#123', 'font-size' => '10px', 'line-height' => '12px'], 'Hello')]);
        $painter = new Painter();
        $list = $painter->paint($fragment);
        self::assertSame(['rectangle', 'push-clip', 'text', 'pop-clip'], array_column($list->getOperations(), 'type'));
        self::assertSame(0.25, $list->getOperations()[0]['x']);
        self::assertSame('Hello', $list->getOperations()[2]['text']);
        self::assertSame($list->getOperations(), $painter->paint($fragment)->getOperations());
        self::assertSame($list->getOperations(), json_decode(json_encode($list->getOperations(), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testCurrentColorIsResolvedBeforeBackendOperations()
    {
        $fragment = new Fragment(null, new Rect(0, 0, 10, 10), ['color' => '#123456', 'background-color' => 'currentColor', 'border-color' => 'currentColor', 'border-width' => '1px']);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertSame('#123456', $operations[0]['color']);
        self::assertSame('#123456', $operations[1]['color']);
    }

    public function testBorderOperationsPreserveDistinctSideStyles()
    {
        $fragment = new Fragment(null, new Rect(0, 0, 10, 10), ['color' => 'red', 'border-top-width' => '1.25px', 'border-top-color' => 'currentColor', 'border-top-style' => 'solid', 'border-left-width' => '2.5px', 'border-left-color' => 'blue', 'border-left-style' => 'dashed']);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertCount(1, $operations);
        self::assertSame('border', $operations[0]['type']);
        self::assertSame(['thickness' => 1.25, 'color' => 'red', 'style' => 'solid'], $operations[0]['sides']['top']);
        self::assertSame(['thickness' => 2.5, 'color' => 'blue', 'style' => 'dashed'], $operations[0]['sides']['left']);
    }
}
