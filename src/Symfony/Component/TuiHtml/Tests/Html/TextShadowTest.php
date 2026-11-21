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
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\LinkedTextWidget;

final class TextShadowTest extends TestCase
{
    public function testTheShadowShowsWhereTheTextLeavesBlank()
    {
        $this->assertSame(['Hi yo', ' Hi yo'], $this->render('<p style="text-shadow: 1ch 1em red">Hi yo</p>'));
        $this->assertSame(['HHi'], $this->render('<p style="text-shadow: -1ch 0">Hi</p>'));
        $this->assertSame(['Hi'], $this->render('<p style="text-shadow: 0 0 2px red">Hi</p>'), 'Only blurred under the text: not drawn.');
        $this->assertSame(['Hi'], $this->render('<p style="text-shadow: 1ch 1em red; text-shadow: none">Hi</p>'));
        $this->assertSame(['Hi'], $this->render('<p style="text-shadow: 1ch 1em 1px 1px red">Hi</p>'), 'No spread.');
        $this->assertSame(['H', 'i'], $this->render('<p style="text-shadow: 1ch 0; width: 1ch">Hi</p>'), 'No room for the shadow beside the text.');
        $this->assertSame(['a bb'], $this->render('<p style="text-shadow: 1ch 0">a b</p>'), 'Not through the spaces of the text: around it.');
    }

    public function testTheShadowIsInheritedAndWrapsWithTheText()
    {
        $this->assertSame(['aaaa bbbb', ' aaaa bbbb'], $this->render('<div style="text-shadow: 1ch 1em; width: 10ch"><p>aaaa bbbb</p></div>'));
        $this->assertSame(['aaaa', 'bbbba', ' bbbb'], $this->render('<div style="text-shadow: 1ch 1em; width: 5ch"><p>aaaa bbbb</p></div>'));
    }

    public function testTheShadowHasItsColor()
    {
        $this->assertStringContainsString("\x1b[0;31mi", $this->draw('<p style="text-shadow: 1ch 0 red">Hi</p>'));
        $this->assertStringContainsString("\x1b[0;2mi", $this->draw('<p style="text-shadow: 1ch 0">Hi</p>'), 'Without a color: the text dimmed.');
    }

    public function testEveryShadowIsDrawnTheFirstOverTheOthers()
    {
        $this->assertSame(['HHii', ' Hi'], $this->render('<p style="text-shadow: 1ch 0 red, -1ch 0 blue, 0 1lh lime">Hi</p>'), 'Around it, on both sides and below, the text moved past the one on the left.');
        $output = $this->draw('<p style="text-shadow: 1ch 0 red, -1ch 0 blue">Hi</p>');
        $this->assertStringContainsString("\x1b[0;34mH", $output, 'The blue one on the left.');
        $this->assertStringContainsString("\x1b[0;31mi", $output, 'The red one on the right.');
        $this->assertStringContainsString("\x1b[0;31mi", $this->draw('<p style="text-shadow: 1ch 0 red, 1ch 0 blue">Hi</p>'), 'Where they meet, the first one.');
        $this->assertSame(['Hi'], $this->render('<p style="text-shadow: 1ch 0 red, bogus">Hi</p>'), 'One not valid: none.');
    }

    public function testTheFocusedLinkKeepsItsCursor()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $view = (new HtmlBuilder())->build('<p id="p" style="text-shadow: 1ch 1em">see <a href="https://example.com">this</a></p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $paragraph = $view->getById('p');
        $this->assertInstanceOf(LinkedTextWidget::class, $paragraph);
        $tui->setFocus($paragraph);
        $lines = $paragraph->render(new RenderContext(20, 5));
        $this->assertCount(2, $lines);
        $at = strpos($lines[0], AnsiUtils::CURSOR_MARKER_PREFIX);
        $this->assertNotFalse($at);
        $this->assertSame('see ', AnsiUtils::stripAnsiCodes(substr($lines[0], 0, $at)), 'The cursor on the link.');
        $this->assertStringContainsString("\x1b]8;", $lines[0], 'The link is kept.');
        $this->assertSame(' see this', AnsiUtils::stripAnsiCodes($lines[1]));
    }

    private function render(string $html): array
    {
        $buffer = new ScreenBuffer(40, 8);
        $buffer->write($this->draw($html));

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }

    private function draw(string $html): string
    {
        $terminal = new VirtualTerminal(40, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build('<style>p { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();

        return $terminal->consumeOutput();
    }
}
