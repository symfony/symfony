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
use Symfony\Bridge\TuiHtml\Render\CellBuffer;
use Symfony\Bridge\TuiHtml\Style\Color;

final class PaintedPixelsTest extends TestCase
{
    public function testHalfBlocksRetainTheirTwoRgbPlanes()
    {
        $buffer = new CellBuffer(2, 1);
        $buffer->writeAnsiLines(["\x1b[38;2;255;0;0;48;2;0;0;255m▀▄\x1b[0m"], 0, 0);
        self::assertSame([[255, 0, 0], [0, 0, 255]], $buffer->getPaintedPixels(0, 0));
        self::assertSame([[0, 0, 255], [255, 0, 0]], $buffer->getPaintedPixels(0, 1));
    }

    public function testReversePaletteDefaultsAndOrdinaryTextUseExistingBufferColorSemantics()
    {
        $buffer = new CellBuffer(3, 1);
        $buffer->writeAnsiLines(["\x1b[7;38;5;196;48;5;21m▀\x1b[0m\x1b[31;44mA\x1b[0m▀"], 0, 0);
        self::assertSame([[0, 0, 255], [255, 0, 0]], $buffer->getPaintedPixels(0, 0));
        $blue = array_values(Color::palette(4)->toRgb());
        self::assertSame([$blue, $blue], $buffer->getPaintedPixels(0, 1));
        self::assertSame([null, null], $buffer->getPaintedPixels(0, 2));
        self::assertSame([null, null], $buffer->getPaintedPixels(1, 0));
    }
}
