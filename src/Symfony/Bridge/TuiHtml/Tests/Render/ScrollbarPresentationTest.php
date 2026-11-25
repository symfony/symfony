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
use Symfony\Bridge\TuiHtml\Internal\ScrollbarPresentation;

final class ScrollbarPresentationTest extends TestCase
{
    public function testSharedGeometryPreservesTheExistingIntegerRoundingMatrix()
    {
        $cases = 0;
        for ($viewport = 1; $viewport <= 40; ++$viewport) {
            for ($content = $viewport; $content <= $viewport + 120; ++$content) {
                $maximum = $content - $viewport;
                foreach (array_unique([0, min(1, $maximum), intdiv($maximum, 2), max(0, $maximum - 1), $maximum]) as $offset) {
                    $length = $content > $viewport ? max(1, intdiv($viewport * $viewport + ($content >> 1), $content)) : $viewport;
                    $position = $content > $viewport ? intdiv($offset * ($viewport - $length) + ($content - $viewport >> 1), $content - $viewport) : 0;
                    self::assertSame([$length, $position], ScrollbarPresentation::cells($viewport, $content, $offset), \sprintf('viewport=%d content=%d offset=%d', $viewport, $content, $offset));
                    ++$cases;
                }
            }
        }
        self::assertSame(23800, $cases);
    }
}
