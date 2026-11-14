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
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

final class FragmentNavigationTest extends TestCase
{
    private const HTML = <<<'HTML'
        <style>:target { font-weight: bold } h2:target::before { content: "→ " }</style>
        <p>Go to the <a href="#end">end</a> or <a href="#nowhere">nowhere</a></p>
        <div id="box" style="overflow: auto; max-height: 3">
          <p>line 1</p><p>line 2</p><p>line 3</p><p>line 4</p><p>line 5</p><p>line 6</p>
          <h2 id="end">The end</h2>
          <p>line 7</p><p>line 8</p><p>line 9</p>
        </div>
        <input id="field">
        HTML;

    public function testAClickOnALinkGoesToItsFragment()
    {
        [$tui, $view, $screen] = $this->mount();
        $box = $view->getById('box');
        $this->assertInstanceOf(ContainerWidget::class, $box);
        $this->assertSame('Go to the end or nowhere', rtrim($screen()[0]), 'Not a terminal link: no mark is left.');
        $this->assertFalse($view->click(0, 2), 'Not on the link.');
        $this->assertTrue($view->click(0, 11));
        $tui->processRender();
        $this->assertSame($view->getById('end'), $view->getTarget());
        $this->assertTrue($view->getById('end')->hasState('target'), ':target matches it.');
        $this->assertSame(6, $box->getScrollTop(), 'Scrolled into view, at the top of its container.');
        $this->assertSame('→ The end', rtrim($screen()[1], ' │┃'));
        // The user scrolls away: it stays there
        $box->scrollTo(0);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(0, $box->getScrollTop());
    }

    public function testALinkWrittenVerticallyIsClickedWhereItIsDrawn()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p style="writing-mode: vertical-rl; height: 6; margin: 0">ab <a href="#t">link</a> cd</p><p id="t">target</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        // Its column, the second from the left, down its four rows
        $this->assertNotNull($tui->getFragmentLinkAt(3, 1));
        $this->assertNull($tui->getFragmentLinkAt(4, 1), 'Not past its last character.');
        $this->assertTrue($view->click(2, 1));
        $this->assertSame($view->getById('t'), $view->getTarget());
    }

    public function testALinkOfTextDrawnLargerIsClickedWhereItIsDrawn()
    {
        $terminal = new VirtualTerminal(30, 8);
        $tui = new Tui(null, $terminal);
        $tui->setMediaContext(new MediaContext(80, 24, \Symfony\Component\TuiHtml\Style\ColorScheme::Dark, \Symfony\Component\TuiHtml\Style\Density::Regular, false, false, false, null, false, 16777216, true));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<h1 style="font-size: 2em; margin: 0">Go <a href="#t">there</a></h1><p id="t">target</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        // Twice as large: "there" from the column 6 to the 15, on both rows of its line
        $this->assertNotNull($tui->getFragmentLinkAt(1, 15));
        $this->assertNull($tui->getFragmentLinkAt(0, 5), 'Not "Go ".');
        $this->assertStringNotContainsString(RichTextWidget::FRAGMENT_LINK, $terminal->consumeOutput(), 'The marks of the renderer are not sent.');
        $this->assertTrue($view->click(0, 8));
        $this->assertSame($view->getById('t'), $view->getTarget());
    }

    public function testScrollPaddingAndMarginKeepRoom()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 12));
        $lines = implode('', array_map(static fn (int $i) => "<p>line {$i}</p>", range(1, 12)));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>#box { overflow: auto; max-height: 6; scroll-padding-top: 1 } #end { scroll-margin-top: 1 } #field { scroll-margin: 1 0 2 }</style><div id="box"><input id="first" aria-label="First">'.$lines.'<h2 id="end">The end</h2>'.$lines.'<input id="field" aria-label="Field">'.$lines.'</div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $box = $view->getById('box');
        $this->assertInstanceOf(ContainerWidget::class, $box);
        $view->navigate('#end');
        $tui->processRender();
        $this->assertSame(11, $box->getScrollTop(), 'Two rows above the heading, at 13: one of padding, one of margin.');
        $tui->setFocus($view->getById('field'));
        $tui->processRender();
        // The field is at row 26: its bottom margin of 2 is kept in view
        $this->assertSame(26 + 1 + 2 - 6, $box->getScrollTop());
    }

    public function testNavigate()
    {
        [$tui, $view] = $this->mount();
        $this->assertTrue($view->navigate('#field'));
        $tui->processRender();
        $this->assertTrue($view->getById('field')->isFocused(), 'What takes the focus gets it.');
        $this->assertFalse($view->navigate('#nowhere'), 'No such element: no target.');
        $this->assertNull($view->getTarget());
        $this->assertFalse($view->getById('field')->hasState('target'));
        $this->assertTrue($view->navigate('end'), 'The hash is optional.');
        $this->assertFalse($view->navigate(''));
        $this->assertFalse($view->getById('end')->hasState('target'));
    }

    public function testATextFragmentGoesToTheFirstMatchOfItsText()
    {
        [$tui, $view] = $this->mount();
        $box = $view->getById('box');
        $this->assertInstanceOf(ContainerWidget::class, $box);
        $this->assertTrue($view->navigate('#:~:text=line%208'));
        $tui->processRender();
        $this->assertSame(7, $box->getScrollTop(), 'Scrolled into view.');
        $this->assertNull($view->getTarget(), 'No element is the target.');
        $this->assertSame('[line 8]', self::marked($box->all()[8]->getText()), 'In the colors of a mark.');
        $this->assertSame('line 7', self::marked($box->all()[7]->getText()));
        // A start and an end, across inline elements; the prefix and the suffix around it, not drawn
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p id="a">one <em>two</em> three four</p><p id="b">x five y five z</p>')->computeStyles();
        $text = static fn (string $id) => self::marked($view->computeStyles()->getById($id)->getText());
        $view->navigate('#:~:text=two,four');
        $this->assertSame('one [two][ three four]', $text('a'));
        $view->navigate('#:~:text=y-,five,-z');
        $this->assertSame('x five y [five] z', $text('b'), 'The five between y and z.');
        $this->assertSame('one two three four', $text('a'), 'The text of the fragment before is not drawn any more.');
        // Whole words only, and nothing when nothing matches
        $this->assertFalse($view->navigate('#:~:text=fiv'));
        $this->assertSame('x five y five z', $text('b'));
    }

    public function testTheTextOfAFragmentIsDrawnAmongManyHighlights()
    {
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>::highlight(h) { text-decoration: underline }</style><p id="p">'.str_repeat('a ', 2500).'target</p>');
        $view->highlight('h', 'a');
        $view->navigate('#:~:text=target');
        $this->assertStringEndsWith('[target]', self::marked($view->computeStyles()->getById('p')->getText()), 'Past the most custom highlights too.');
    }

    public function testAnIdAndATextFragment()
    {
        [$tui, $view] = $this->mount();
        $this->assertTrue($view->navigate('#end:~:text=line%201'));
        $tui->processRender();
        $this->assertSame($view->getById('end'), $view->getTarget(), 'The element is the target, the text drawn too.');
        $this->assertSame('[line 1]', self::marked($view->getById('box')->all()[0]->getText()));
    }

    private static function marked(string $text): string
    {
        $text = preg_replace('/\e\[[0-9;]*48;2;255;255;0m(.*?)\e\[[0-9;]*49m/', '[$1]', $text);

        return preg_replace('/\e\[[0-9;]*m/', '', $text);
    }

    private function mount(): array
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build(self::HTML)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(40, 10);

        return [$tui, $view, static function () use ($terminal, $buffer): array {
            $buffer->write($terminal->consumeOutput());

            return explode("\n", $buffer->getScreen());
        }];
    }
}
