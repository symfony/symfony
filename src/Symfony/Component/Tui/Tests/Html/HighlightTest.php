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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class HighlightTest extends TestCase
{
    public function testTheTextsAreDrawnInTheStyleOfTheirRules()
    {
        $view = $this->build('<p>Find the needle in the Needle stack</p>');
        $view->highlight('found', 'needle');
        $this->assertSame("Find the \e[1mneedle\e[22m in the \e[1mNeedle\e[22m stack", $this->text($view), 'The case does not matter.');

        $view->highlight('found', 'needle', matchCase: true);
        $this->assertSame("Find the \e[1mneedle\e[22m in the Needle stack", $this->text($view));

        $view->removeHighlight('found');
        $this->assertSame('Find the needle in the Needle stack', $this->text($view));
    }

    public function testATextIsFoundAcrossInlineElementsAndWhitespace()
    {
        $view = $this->build("<p>a <em>big</em>\n   cat</p>");
        $view->highlight('found', 'big cat');
        $this->assertSame("a \e[1m\e[3mbig\e[23m\e[22m\e[1m cat\e[22m", $this->text($view), 'Over the italic of the em, the whitespace collapsed.');
    }

    public function testALaterHighlightIsOverTheOthers()
    {
        $view = $this->build('<p>abcdef</p>');
        $view->highlight('found', 'abcd');
        $view->highlight('other', 'cdef');
        $this->assertSame("\e[1mab\e[22m\e[1m\e[29m\e[55m\e[4mcd\e[24m\e[22m\e[29m\e[55m\e[4mef\e[24m", $this->text($view));
    }

    public function testAHighlightWithoutRulesIsNotDrawn()
    {
        $view = $this->build('<p>abc</p>');
        $view->highlight('unstyled', 'b');
        $this->assertSame('abc', $this->text($view));

        $this->expectException(InvalidArgumentException::class);
        $view->highlight('not a name', 'b');
    }

    public function testTheHighlightIsDrawnAgainWhenItChanges()
    {
        $view = $this->build('<p>one two</p>');
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(terminal: $terminal);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $terminal->consumeOutput();

        $view->highlight('found', 'two');
        $tui->processRender();
        $this->assertStringContainsString("\e[1mtwo", $terminal->consumeOutput());
    }

    private function build(string $html): HtmlView
    {
        return (new HtmlBuilder())->build('<style>::highlight(found) { font-weight: bold } ::highlight(other) { text-decoration: underline }</style>'.$html);
    }

    private function text(HtmlView $view): string
    {
        return $view->computeStyles()->getBody()->all()[0]->getText();
    }
}
