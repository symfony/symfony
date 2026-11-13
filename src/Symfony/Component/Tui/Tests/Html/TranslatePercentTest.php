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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class TranslatePercentTest extends TestCase
{
    public function testAnAbsoluteElementIsCenteredByItsOwnSize()
    {
        $terminal = new VirtualTerminal(40, 12);
        $tui = new Tui(terminal: $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.box { position: relative; height: 10 } .center { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 10ch; height: 2 } .nudged { position: relative; width: 20ch; transform: translateX(50%) }</style>
            <div class="box"><p class="center" id="center">centered</p></div><p class="nudged" id="nudged">moved by half its width</p>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $center = $tui->getWidgetRect($view->getById('center'));
        $this->assertSame([4, 15, 10, 2], [$center->row, $center->col, $center->columns, $center->rows], 'Its middle at the middle of the box: 20 - 5, 5 - 1.');

        $buffer = new ScreenBuffer(40, 12);
        $buffer->write($terminal->consumeOutput());
        $line = array_values(array_filter(explode("\n", $buffer->getScreen()), static fn (string $line) => str_contains($line, 'moved')))[0] ?? '';
        $this->assertSame(10, strspn($line, ' '), 'A relative one moves by half its own width, 20 columns.');
    }

    public function testAPercentageMixedWithALengthInCalc()
    {
        // Like the glider of the tabs of the exception page of Symfony, translateX(calc(var(--i) * 100%))
        $terminal = new VirtualTerminal(40, 4);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<p style="position: relative; width: 10ch; transform: translateX(calc(1 * 100% + 2ch))">moved</p>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $buffer = new ScreenBuffer(40, 4);
        $buffer->write($terminal->consumeOutput());
        $this->assertSame(12, strspn(explode("\n", $buffer->getScreen())[0], ' '), 'Its own width and 2 columns.');
    }
}
