<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\TuiHtml\Render\Compositor;
use Symfony\Component\TuiHtml\Render\Layer;

class CompositorTest extends TestCase
{
    public function testCanvasIsAsWideAsTheWidestBaseLine()
    {
        $lines = Compositor::composite(new Layer(['ab', 'a longer line here', 'abc']));
        $this->assertSame(['ab                ', 'a longer line here', 'abc               '], $lines);
    }

    public function testAnExplicitCanvasWidthStillWins()
    {
        $lines = Compositor::composite(new Layer(['ab', 'a longer line here'], 0, 0, false, 4));
        $this->assertSame(['ab  ', 'a lo'], $lines);
    }

    public function testAnOverlayLandsOnTheRowsBelowTheFirst()
    {
        $lines = Compositor::composite(new Layer(['..', '..................']), new Layer(['XX'], 1, 16));
        $this->assertSame(18, AnsiUtils::visibleWidth($lines[1]));
        $this->assertSame('................XX', $lines[1]);
    }

    public function testTheSpacesOnTheBackgroundATransparentLayerSeesThroughShowWhatIsUnderThem()
    {
        $white = "\x1b[48;2;255;255;255m";
        $under = [$white.'Heading'."\x1b[0m"];
        $over = [$white.'  it   '."\x1b[0m"];
        $this->assertSame(['  it   '], array_map(AnsiUtils::stripAnsiCodes(...), Compositor::composite(new Layer($under), new Layer($over, 0, 0, true))));
        $this->assertSame(['Heiting'], array_map(AnsiUtils::stripAnsiCodes(...), Compositor::composite(new Layer($under), new Layer($over, 0, 0, true, null, null, '48;2;255;255;255'))));
    }

    public function testAnEmptyBaseComposesToNothing()
    {
        $this->assertSame([], Compositor::composite(new Layer([])));
    }
}
