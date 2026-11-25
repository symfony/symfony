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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

class NativeGeneratedClearTest extends TestCase
{
    public function testGeneratedClearUsesTheCoreResolvedCustomProperty()
    {
        foreach (['both', 'var(--side)'] as $clear) {
            $terminal = new VirtualTerminal(20, 10);
            $tui = new Tui(null, $terminal);
            try {
                $view = new HtmlBuilder()->setDefaultStyles(false)->build('<style>#target{--side:both}#target::before{content:"";display:block;clear:'.$clear.'}</style><div style="float:left;width:3ch;height:3">F</div><div id="target">Cleared</div>')->mount($tui);
                $tui->start();
                $tui->processRender();
                $buffer = new ScreenBuffer(20, 10);
                $buffer->write($terminal->consumeOutput());
                $lines = array_map('rtrim', explode("\n", $buffer->getScreen()));
                self::assertSame('Cleared', $lines[3] ?? null, $clear);
            } finally {
                $tui->stop();
            }
        }
    }
}
