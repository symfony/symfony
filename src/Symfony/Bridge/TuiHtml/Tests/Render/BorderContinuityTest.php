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
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\Tui\Ansi\AnsiUtils;

class BorderContinuityTest extends TestCase
{
    public function testAdjacentBorderRowsReuseTheLegacyGlyphContinuity()
    {
        self::assertSame(['┌─┐', '│ │'], $this->paint([$this->border(0, ['top']), $this->border(16, ['left', 'right'])]));
    }

    public function testContainedBorderRowsDoNotAcquireSiblingJunctions()
    {
        $parent = $this->border(0, ['top']);
        $parent['height'] = 32.0;
        self::assertSame(['───', '│ │'], $this->paint([$parent, $this->border(16, ['left', 'right'])]));
    }

    public function testOrdinaryAndOpaqueNativeTextGlyphsDoNotAcquireBorderOwnership()
    {
        self::assertSame(['───', '│ │'], $this->paint([$this->text(0, '───'), $this->border(16, ['left', 'right'])]));
        self::assertSame(['───', '│ │'], $this->paint([$this->border(0, ['top']), $this->text(16, '│ │')]));
    }

    public function testLaterPaintOccludesBorderOwnershipEvenWhenItUsesTheSameGlyph()
    {
        self::assertSame(['───', '│ │'], $this->paint([$this->border(0, ['top']), $this->border(16, ['left', 'right']), $this->text(0, '───')]));
        self::assertSame(['   ', '│ │'], $this->paint([$this->border(0, ['top']), $this->border(16, ['left', 'right']), ['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => 24.0, 'height' => 16.0, 'color' => 'blue']]));
    }

    public function testClippedBorderEdgesDoNotJoinHiddenCells()
    {
        self::assertSame(['──┐', '  │'], $this->paint([$this->border(0, ['top']), ['type' => 'push-clip', 'x' => 8.0, 'y' => 16.0, 'width' => 16.0, 'height' => 16.0], $this->border(16, ['left', 'right']), ['type' => 'pop-clip']]));
    }

    public function testFilteredLayersPreserveOnlyActualBorderOwnership()
    {
        $start = ['type' => 'push-filter', 'functions' => [['brightness', 0.5]]];
        $end = ['type' => 'pop-filter'];
        self::assertSame(['┌─┐', '│ │'], $this->paint([$this->border(0, ['top']), $start, $this->border(16, ['left', 'right']), $end]));
        self::assertSame(['───', '│ │'], $this->paint([$this->border(0, ['top']), $start, $this->text(16, '│ │'), $end]));
    }

    private function paint(array $operations): array
    {
        return array_map(AnsiUtils::stripAnsiCodes(...), new DisplayListRenderer()->render(new DisplayList($operations), 3, 2));
    }

    private function text(float $y, string $text): array
    {
        return ['type' => 'text', 'x' => 0.0, 'y' => $y, 'width' => 24.0, 'height' => 16.0, 'text' => $text, 'color' => 'white'];
    }

    private function border(float $y, array $visible): array
    {
        $sides = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $sides[$side] = ['thickness' => \in_array($side, $visible, true) ? 8.0 : 0.0, 'style' => 'solid', 'color' => 'white'];
        }

        return ['type' => 'border', 'x' => 0.0, 'y' => $y, 'width' => 24.0, 'height' => 16.0, 'sides' => $sides, 'color' => 'white'];
    }
}
