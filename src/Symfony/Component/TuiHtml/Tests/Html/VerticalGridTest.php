<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

class VerticalGridTest extends TestCase
{
    public function testTheChildrenOfAnElementWithoutABoxArePlacedLikeItems()
    {
        $grid = '<div style="display: grid; writing-mode: vertical-rl; grid-template-columns: 2 2; grid-template-rows: 2ch 2ch; height: 4">%s</div>';
        $direct = $this->screen(\sprintf($grid, '<p style="grid-row: 2">ab</p><p style="grid-row: 1">cd</p>'));
        $wrapped = $this->screen(\sprintf($grid, '<div style="display: contents"><p style="grid-row: 2">ab</p><p style="grid-row: 1">cd</p></div>'));
        $this->assertSame($direct, $wrapped);
    }

    public function testAnInvalidWritingModeSwapsNothing()
    {
        $grid = '<div style="display: grid; %s grid-template-columns: 4ch 4ch"><p style="grid-column: 2">ab</p><p style="grid-column: 1">cd</p></div>';
        $this->assertSame($this->screen(\sprintf($grid, '')), $this->screen(\sprintf($grid, 'writing-mode: vertical;')));
    }

    private function screen(string $html): array
    {
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 6);
        $buffer->write($terminal->consumeOutput());
        $tui->stop();

        return array_map('rtrim', explode("\n", $buffer->getScreen()));
    }
}
