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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class HighlightTest extends TestCase
{
    public function testTheTextsAreDrawnInTheStyleOfTheirRules()
    {
        $view = $this->build('<p>Find the needle in the Needle stack</p>');
        $view->highlight('found', 'needle');
        $this->assertSame("Find the \x1b[1mneedle\x1b[22m in the \x1b[1mNeedle\x1b[22m stack", $this->text($view), 'The case does not matter.');
        $view->highlight('found', 'needle', true);
        $this->assertSame("Find the \x1b[1mneedle\x1b[22m in the Needle stack", $this->text($view));
        $view->removeHighlight('found');
        $this->assertSame('Find the needle in the Needle stack', $this->text($view));
    }

    public function testATextIsFoundAcrossInlineElementsAndWhitespace()
    {
        $view = $this->build("<p>a <em>big</em>\n   cat</p>");
        $view->highlight('found', 'big cat');
        $this->assertSame("a \x1b[1m\x1b[3mbig\x1b[23m\x1b[22m\x1b[1m cat\x1b[22m", $this->text($view), 'Over the italic of the em, the whitespace collapsed.');
    }

    public function testALaterHighlightIsOverTheOthers()
    {
        $view = $this->build('<p>abcdef</p>');
        $view->highlight('found', 'abcd');
        $view->highlight('other', 'cdef');
        $this->assertSame("\x1b[1mab\x1b[22m\x1b[1m\x1b[29m\x1b[55m\x1b[4mcd\x1b[24m\x1b[22m\x1b[29m\x1b[55m\x1b[4mef\x1b[24m", $this->text($view));
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
        $tui = new Tui(null, $terminal);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $terminal->consumeOutput();
        $view->highlight('found', 'two');
        $tui->processRender();
        $this->assertStringContainsString("\x1b[1mtwo", $terminal->consumeOutput());
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
