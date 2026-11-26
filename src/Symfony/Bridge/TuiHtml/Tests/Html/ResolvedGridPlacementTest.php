<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Render\LayoutEngine;
use Symfony\Bridge\TuiHtml\Render\Renderer;
use Symfony\Bridge\TuiHtml\Style\GridPlacement;
use Symfony\Bridge\TuiHtml\Style\GridTemplate;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Widget\TextWidget;

final class ResolvedGridPlacementTest extends TestCase
{
    #[DataProvider('columnFlowCases')]
    public function testColumnFlowPreservesSparseOrderAndImplicitRowSpans(array $items, bool $dense, array $expected)
    {
        $children = [];
        foreach ($items as $index => [$row, $column, $rowSpan, $columnSpan]) {
            $child = new TextWidget((string) $index);
            StyleBridge::set($child, (new Style())->withGridPlacement(new GridPlacement(null, null === $column ? null : $column + 1, null, $columnSpan, null === $row ? null : $row + 1, null, $rowSpan)));
            $children[] = $child;
        }
        $reflection = new \ReflectionClass(LayoutEngine::class);
        $engine = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('widgetRenderer')->setValue($engine, new Renderer());
        $template = new GridTemplate(array_fill(0, 3, ['fr', 1.0, 0]), null, [1, 1, 1], [], null, $dense, [], [], true);
        $lead = $rowLead = 0;
        $arguments = [$children, $template, 3, false, &$lead, &$rowLead];
        $placed = $reflection->getMethod('placeGridItems')->invokeArgs($engine, $arguments);

        self::assertSame($expected, array_map(static fn (array $item): array => \array_slice($item, 1), $placed));
        self::assertSame($children, array_column($placed, 0));
        self::assertSame([0, 0], [$lead, $rowLead]);
    }

    public function testNativeConstructorCoordinatesAndSpansRemainBounded()
    {
        foreach ([false, true] as $columnFlow) {
            foreach ([[\PHP_INT_MIN, 1, 1, 1, [0, 0, 1, 1], [0, 100]], [1, \PHP_INT_MIN, 1, 1, [0, 0, 1, 1], [100, 0]], [1, 1, 0, -5, [0, 0, 1, 1], [0, 0]], [1, 1, -5, 0, [0, 0, 1, 1], [0, 0]], [1, 1, \PHP_INT_MAX, \PHP_INT_MAX, [0, 0, 100, 100], [0, 0]], [1000, 1000, 1, 1, [999, 999, 1, 1], [0, 0]]] as [$row, $column, $rowSpan, $columnSpan, $expected, $leads]) {
                [$placed, $actualLeads] = $this->placeNative(new GridPlacement(null, $column, null, $columnSpan, $row, null, $rowSpan), $columnFlow);
                self::assertSame([$expected], $placed);
                self::assertSame($leads, $actualLeads);
            }
        }
    }

    public function testNativeAreaCoordinatesAndSpansUseTheSameBounds()
    {
        foreach ([false, true] as $columnFlow) {
            [$placed, $leads] = $this->placeNative(new GridPlacement('a'), $columnFlow, ['a' => [-1000, -1000, -1000, -1000]]);
            self::assertSame([[0, 0, 1, 1]], $placed);
            self::assertSame([100, 100], $leads);
        }
    }

    #[DataProvider('outsideTrackLimitCases')]
    public function testNativeExtentsOutsideTheSharedTrackLimitAreRejected(int $row, int $column, bool $columnFlow)
    {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('A grid cannot generate more than 1000 tracks along either axis.');
        $this->placeNative(new GridPlacement(null, $column, null, 1, $row), $columnFlow);
    }

    public static function outsideTrackLimitCases(): iterable
    {
        foreach ([false, true] as $columnFlow) {
            yield [\PHP_INT_MAX, 1, $columnFlow];
            yield [1, \PHP_INT_MAX, $columnFlow];
            yield [1001, 1, $columnFlow];
            yield [1, 1001, $columnFlow];
        }
    }

    public static function columnFlowCases(): iterable
    {
        yield 'sparse locked column does not backfill' => [[[1, 0, 1, 1], [null, 0, 2, 1], [null, 0, 1, 1]], false, [[1, 0, 1, 1], [2, 0, 2, 1], [4, 0, 1, 1]]];
        yield 'sparse successive spans' => [[[1, 0, 1, 1], [null, 0, 2, 1], [null, 0, 1, 1], [null, 0, 2, 1], [null, 1, 1, 1]], false, [[1, 0, 1, 1], [2, 0, 2, 1], [4, 0, 1, 1], [5, 0, 2, 1], [0, 1, 1, 1]]];
        yield 'explicit cells around sparse cursor' => [[[3, 0, 1, 1], [null, 0, 1, 1], [1, 0, 1, 1], [null, 0, 2, 1], [null, 0, 1, 1]], false, [[3, 0, 1, 1], [0, 0, 1, 1], [1, 0, 1, 1], [4, 0, 2, 1], [6, 0, 1, 1]]];
        yield 'implicit row span sparse' => [[[null, null, 4, 1], [null, null, 1, 1]], false, [[0, 0, 4, 1], [0, 1, 1, 1]]];
        yield 'implicit row span dense' => [[[null, null, 4, 1], [null, null, 1, 1]], true, [[0, 0, 4, 1], [0, 1, 1, 1]]];
        yield 'locked implicit row span sparse' => [[[0, null, 4, 1], [null, null, 1, 1]], false, [[0, 0, 4, 1], [0, 1, 1, 1]]];
        yield 'locked implicit row span dense' => [[[0, null, 4, 1], [null, null, 1, 1]], true, [[0, 0, 4, 1], [0, 1, 1, 1]]];
        yield 'dense backfills earlier gap' => [[[1, 0, 1, 1], [null, 0, 2, 1], [null, 0, 1, 1]], true, [[1, 0, 1, 1], [2, 0, 2, 1], [0, 0, 1, 1]]];
    }

    private function placeNative(GridPlacement $placement, bool $columnFlow, array $areas = []): array
    {
        $child = new TextWidget('bounded');
        StyleBridge::set($child, (new Style())->withGridPlacement($placement));
        $reflection = new \ReflectionClass(LayoutEngine::class);
        $engine = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('widgetRenderer')->setValue($engine, new Renderer());
        $template = new GridTemplate([['fr', 1.0, 0]], null, [1], $areas, null, false, [], [], $columnFlow);
        $lead = $rowLead = 0;
        $arguments = [[$child], $template, 1, false, &$lead, &$rowLead];
        $placed = $reflection->getMethod('placeGridItems')->invokeArgs($engine, $arguments);

        return [array_map(static fn (array $item): array => \array_slice($item, 1), $placed), [$lead, $rowLead]];
    }
}
