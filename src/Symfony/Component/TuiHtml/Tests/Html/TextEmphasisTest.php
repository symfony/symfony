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

final class TextEmphasisTest extends TestCase
{
    public function testMarksAreOverEachCharacterButTheSpacesAndThePunctuation()
    {
        $this->assertSame(['   ●● ●●●●', 'It is very, sure.'], $this->render('<p>It <em style="text-emphasis: filled circle">is very</em>, sure.</p>'));
        $this->assertSame(['  ◦◦', 'a do b'], $this->render('<p>a <span style="text-emphasis-style: open dot">do</span> b</p>'));
    }

    public function testUnderAStringAndInheritance()
    {
        $this->assertSame(['ab cd', '** **'], $this->render('<p style="text-emphasis: \'*\'; text-emphasis-position: under">ab <span>cd</span></p>'), 'Under the line, inherited by the span.');
        $this->assertSame(['xy'], $this->render('<p style="text-emphasis: dot"><span style="text-emphasis: none">xy</span></p>'), 'none turns them off.');
    }

    public function testTheMarksOfWideCharacters()
    {
        $this->assertSame(['﹅﹅', '日本'], $this->render('<p style="text-emphasis: sesame">日本</p>'), 'A sesame is as wide as the character.');
        $this->assertSame(['● ●', '日本'], $this->render('<p style="text-emphasis: circle">日本</p>'), 'A narrower mark in its first column, a half one not being drawn.');
    }

    public function testTheShorthandOfAnElementWinsOverTheLonghandsItInherits()
    {
        $this->assertSame(['••', 'ab cd'], $this->render('<style>.s { text-emphasis-style: dot } .s em { text-emphasis: none }</style><p class="s">ab <em>cd</em></p>'));
        $this->assertSame(['   •', 'ab c'], $this->render('<style>.c { text-emphasis-color: blue } .c i { text-emphasis: dot }</style><p class="c">ab <i>c</i></p>'));
    }

    public function testARubyKeepsItsAnnotationInEmphasizedText()
    {
        $this->assertSame(['kan•', '漢 字'], $this->render('<p style="text-emphasis: dot"><ruby>漢<rt>kan</rt></ruby>字</p>'));
    }

    public function testAWordBrokenAcrossLinesKeepsTheMarksOfAllItsCharacters()
    {
        $this->assertSame(['••••••', 'abcdef', '••••', 'ghij'], $this->render('<p style="width: 6ch; text-emphasis: dot">abcdefghij</p>'));
    }

    public function testTheTextAlternativeOfAnImageHasNoMarks()
    {
        $this->assertSame(['•     •', 'x[pic]y'], $this->render('<p style="text-emphasis: dot">x<img alt="pic">y</p>'));
    }

    public function testThePlainTextHasNoMarks()
    {
        $view = (new HtmlBuilder())->build('<p id="p">Read <em style="text-emphasis: dot">this</em> <ruby>漢<rt>かん</rt></ruby></p>');
        $view->computeStyles();
        $this->assertSame('Read this 漢(かん)', $view->getById('p')->getPlainText(), 'The reading of a ruby stays, the marks are not read.');
    }

    private function render(string $html): array
    {
        $terminal = new VirtualTerminal(30, 6);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build('<style>p { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 6);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
