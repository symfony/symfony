<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class FlowMarginsTest extends TestCase
{
    public function testClearanceIsStableWhenTheLayoutIsRepeatedWithoutCaches()
    {
        $terminal = new VirtualTerminal(30, 20);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.f { float: left; width: 5ch; height: 8 } p { margin: 0 } #a { margin-bottom: 3 } #c::before { content: ""; display: block; clear: both } #c p { margin-top: 3 }</style><div class="f">F</div><p id="a">A</p><div id="c"><p>B</p></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 20);
        $buffer->write($terminal->consumeOutput());
        $first = $buffer->getScreen();
        foreach ($view->querySelectorAll('*') as $widget) {
            $widget->invalidate();
        }
        $tui->requestRender();
        $tui->processRender();
        $buffer->write($terminal->consumeOutput());
        $this->assertSame($first, $buffer->getScreen());
        $tui->stop();
    }

    #[DataProvider('emptyBlocks')]
    public function testAnEmptyBlockJoinsItsNeighboursMarginGroup(string $empty, int $gap)
    {
        $terminal = new VirtualTerminal(20, 20);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } #a { margin-bottom: 3lh } #empty { margin: 1lh 0 } #b { margin-top: 3lh }</style><p id="a">A</p>'.$empty.'<p id="b">B</p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 20);
        $buffer->write($terminal->getOutput());
        $tui->stop();

        $this->assertSame('A'.str_repeat("\n", $gap + 1).'B', $buffer->getScreen());
    }

    public function testAnEmptyMarginGroupBesideAFloat()
    {
        $terminal = new VirtualTerminal(20, 12);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } #f { float: left; width: 3ch; height: 8 } #a { margin-bottom: 3 } #empty { margin: 1 0 } #b { margin-top: 3 }</style><div id="f">F</div><p id="a">A</p><div id="empty"></div><p id="b">B</p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 12);
        $buffer->write($terminal->getOutput());
        $lines = explode("\n", $buffer->getScreen());
        $this->assertSame(4, array_find_key($lines, static fn (string $line) => str_contains($line, 'B')));
        $this->assertSame('F   A', $lines[0]);
        $tui->stop();
    }

    public static function emptyBlocks(): iterable
    {
        yield ['<div id="empty"></div>', 3];
        yield ['<div id="empty"></div><div style="margin: 2lh 0"></div>', 3];
        yield ['<div id="empty"><div style="margin: 2lh 0"></div></div>', 3];
        yield ['<div style="margin: 5lh 0"></div>', 5];
    }
}
