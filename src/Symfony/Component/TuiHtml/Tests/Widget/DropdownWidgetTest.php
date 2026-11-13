<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\DropdownWidget;

final class DropdownWidgetTest extends TestCase
{
    public function testEscapeClosesItAsItWas()
    {
        [$tui, , $dropdown] = $this->mount();
        $this->assertSame($dropdown->getField(), $tui->getFocus());
        $tui->handleInput("\r");
        $this->assertTrue($dropdown->isOpen());
        $this->assertTrue($dropdown->hasState('open'), 'select:open matches it.');
        $this->assertSame($dropdown->getList(), $tui->getFocus());
        $tui->handleInput("\x1b[B");
        $tui->handleInput("\x1b");
        $this->assertFalse($dropdown->isOpen());
        $this->assertSame('b', $dropdown->getList()->getSelectedItem()['value'] ?? null, 'The choice before opening.');
        $this->assertSame($dropdown->getField(), $tui->getFocus());
    }

    public function testDownOpensItAndTheFieldKeepsTheChoiceUntilTaken()
    {
        [$tui, , $dropdown] = $this->mount();
        $tui->handleInput("\x1b[B");
        $this->assertTrue($dropdown->isOpen());
        $tui->handleInput("\x1b[B");
        $this->assertSame(['b', '▾'], [$dropdown->getField()->getText(), $dropdown->getField()->getIcon()], 'The list is on c, the choice is still b.');
        $tui->handleInput("\r");
        $this->assertSame('c', $dropdown->getField()->getText());
    }

    public function testTheFocusLeavingClosesIt()
    {
        [$tui, $view, $dropdown] = $this->mount();
        $dropdown->setOpen(true);
        $tui->processRender();
        $tui->setFocus($view->getById('after'));
        $tui->processRender();
        $this->assertFalse($dropdown->isOpen());
    }

    public function testALabelClickOpensTheFieldAndTheNameIsTheFields()
    {
        [$tui, $view, $dropdown] = $this->mount();
        $tui->setFocus($view->getById('after'));
        $tui->processRender();
        $this->assertTrue($view->click(0, 1), 'The label.');
        $this->assertSame($dropdown->getField(), $tui->getFocus());
        $this->assertSame('Pick', $dropdown->getField()->getLabel());
    }

    public function testThePickerIconAndTheCheckmarkTakeTheirContent()
    {
        [$tui, , $dropdown] = $this->mount('select::picker-icon { content: "+" } select::checkmark { content: "*" } .plain::picker-icon, .plain::checkmark { content: none }');
        $this->assertSame('+', $dropdown->getField()->getIcon());
        $dropdown->setOpen(true);
        $tui->processRender();
        $this->assertSame(['  a', '* b', '  c'], array_map(static fn (string $line) => rtrim(AnsiUtils::stripAnsiCodes($line), ' │'), \array_slice($dropdown->getList()->render(new RenderContext(10, 5)), 0, 3)));
        [$tui, , $dropdown] = $this->mount('', 'plain');
        $this->assertSame('▾', $dropdown->getField()->getIcon(), 'The default ones without content.');
        [$tui, , $dropdown] = $this->mount('.plain::picker-icon, .plain::checkmark { content: none }', 'plain');
        $this->assertSame('', $dropdown->getField()->getIcon(), 'none hides them.');
        $this->assertSame(['a', 'b', 'c'], array_map(static fn (string $line) => rtrim(AnsiUtils::stripAnsiCodes($line)), \array_slice($dropdown->getList()->render(new RenderContext(10, 5)), 0, 3)));
    }

    public function testTheCheckmarkContentHasNoControlBytes()
    {
        [, , $dropdown] = $this->mount('select::checkmark { content: "\1b[2J*" } select::picker-icon { content: "\1b]0;x\7+" }');
        $rows = $dropdown->getList()->render(new RenderContext(10, 5));
        $this->assertStringNotContainsString("\x1b[2J", implode('', $rows));
        $this->assertSame('[2J* b', rtrim(AnsiUtils::stripAnsiCodes($rows[1])), 'The escape byte is dropped, the rest is text.');
        $this->assertSame(']0;x+', $dropdown->getField()->getIcon());
    }

    public function testTheElementsOfOptionsAreStyled()
    {
        $tui = new Tui(null, new VirtualTerminal(30, 10));
        $view = (new HtmlBuilder())->build('<style>option .ko { color: red }</style><select id="s" aria-label="x"><option>Paris <span class="ok">up</span></option><option selected>Boston <span class="ko">down</span></option></select>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $dropdown = $view->getById('s');
        $this->assertSame('Boston '.Color::named('red')->toForegroundCode().'down'."\x1b[39m", $dropdown->getField()->getText(), 'The field shows the chosen content, styled.');
        $this->assertSame(['Paris up', 'Boston down'], array_map(static fn (array $item) => $item['label'], $dropdown->getList()->getItems()), 'The labels events carry stay text.');
        $this->assertSame('Boston down', $dropdown->getList()->getItems()[1]['value'], 'The value is the text, without styles.');
        $tui->stop();
    }

    private function mount(string $css = '', string $class = ''): array
    {
        $tui = new Tui(null, new VirtualTerminal(30, 10));
        $view = (new HtmlBuilder())->build('<style>'.$css.'</style><label for="s">Pick</label><select id="s" class="'.$class.'"><option>a</option><option selected>b</option><option>c</option></select><button id="after">After</button>')->mount($tui);
        $tui->start();
        // The first Tab, like browsers: nothing has the focus before it
        $tui->handleInput("\x1b[17~");
        $tui->processRender();
        $dropdown = $view->getById('s');
        $this->assertInstanceOf(DropdownWidget::class, $dropdown);

        return [$tui, $view, $dropdown];
    }
}
