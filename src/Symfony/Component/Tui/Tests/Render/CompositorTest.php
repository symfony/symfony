<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\Compositor;
use Symfony\Component\Tui\Render\Layer;

class CompositorTest extends TestCase
{
    public function testCanvasIsAsWideAsTheWidestBaseLine()
    {
        $lines = Compositor::composite(new Layer(['ab', 'a longer line here', 'abc']));

        $this->assertSame(['ab                ', 'a longer line here', 'abc               '], $lines);
    }

    public function testAnExplicitCanvasWidthStillWins()
    {
        $lines = Compositor::composite(new Layer(['ab', 'a longer line here'], width: 4));

        $this->assertSame(['ab  ', 'a lo'], $lines);
    }

    public function testAnOverlayLandsOnTheRowsBelowTheFirst()
    {
        $lines = Compositor::composite(
            new Layer(['..', '..................']),
            new Layer(['XX'], row: 1, col: 16),
        );

        $this->assertSame(18, AnsiUtils::visibleWidth($lines[1]));
        $this->assertSame('................XX', $lines[1]);
    }

    public function testAnEmptyBaseComposesToNothing()
    {
        $this->assertSame([], Compositor::composite(new Layer([])));
    }
}
