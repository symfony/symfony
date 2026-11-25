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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache;

#[RequiresPhpExtension('gd')]
final class BackgroundImageCacheTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="8" height="16"><rect width="8" height="16" fill="red"/></svg>';

    public function testSharedSourcesReuseRasterAndOneCellVariantAcrossFrames()
    {
        $cache = new BackgroundImageCache();
        $first = $cache->get(self::SVG, 'image/svg+xml', true, false, 1, 1);
        self::assertNotNull($first);
        $cells = $first->cellAt(1, 1, 0, 0);
        for ($frame = 0; $frame < 2; ++$frame) {
            for ($i = 0; $i < 100; ++$i) {
                self::assertSame($first, $cache->get(self::SVG, 'image/svg+xml', true, false, 1, 1));
            }
        }
        self::assertSame($cells, $first->cellAt(1, 1, 0, 0));
        self::assertNotSame($first, $cache->get(self::SVG, 'image/svg+xml', true, true, 1, 1));
        self::assertNotSame($first, $cache->get(self::SVG, 'image/svg+xml', true, false, 2, 1));
        self::assertNotSame($first, new BackgroundImageCache()->get(self::SVG, 'image/svg+xml', true, false, 1, 1));
    }

    public function testAggregateCellReservationEvictsBeforeExceedingTheBudget()
    {
        $cache = new BackgroundImageCache(32, 16777216, 4194304, 2);
        $first = $cache->get(self::SVG, 'image/svg+xml', true, false, 1, 1);
        $secondSource = str_replace('red', 'blue', self::SVG);
        $second = $cache->get($secondSource, 'image/svg+xml', true, false, 1, 1);
        $cache->get(str_replace('red', 'green', self::SVG), 'image/svg+xml', true, false, 1, 1);
        self::assertSame($second, $cache->get($secondSource, 'image/svg+xml', true, false, 1, 1));
        self::assertNotSame($first, $cache->get(self::SVG, 'image/svg+xml', true, false, 1, 1));
    }

    public function testFifoEvictionAndBytePixelCellBudgetsAreIndependent()
    {
        $other = str_replace('red', 'blue', self::SVG);
        $cache = new BackgroundImageCache(1);
        $first = $cache->get(self::SVG, 'image/svg+xml', true, false, 1, 1);
        $cache->get($other, 'image/svg+xml', true, false, 1, 1);
        self::assertNotSame($first, $cache->get(self::SVG, 'image/svg+xml', true, false, 1, 1));
        foreach ([new BackgroundImageCache(32, 1), new BackgroundImageCache(32, 16777216, 1), new BackgroundImageCache(32, 16777216, 4194304, 1)] as $cache) {
            $first = $cache->get(self::SVG, 'image/svg+xml', true, false, 2, 1);
            self::assertNotNull($first, 'An uncacheable image remains renderable.');
            self::assertNotSame($first, $cache->get(self::SVG, 'image/svg+xml', true, false, 2, 1));
        }
    }
}
