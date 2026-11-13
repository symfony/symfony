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
use Symfony\Component\Tui\Exception\LogicException;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Tests\Fixtures\CollectingLogger;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\InputWidget;

final class HtmlViewTest extends TestCase
{
    public function testGetByIdAndQuerySelector()
    {
        $view = (new HtmlBuilder())->build('<main><p id="a" class="item">a</p><section><p class="item">b</p></section></main>');
        $this->assertSame('a', $view->getById('a')->getText());
        $this->assertSame('a', $view->querySelector('.item')->getText());
        $this->assertSame(['a', 'b'], array_map(static fn ($w) => $w->getText(), $view->querySelectorAll('main .item')));
        $this->assertSame('b', $view->querySelector('section > p')->getText());
        $this->assertNull($view->querySelector('h1'));
    }

    public function testGetByIdThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        (new HtmlBuilder())->build('<p>a</p>')->getById('nope');
    }

    public function testNoRoot()
    {
        $this->expectException(LogicException::class);
        (new HtmlView(new CssStyleSheet()))->getRoot();
    }

    public function testMountAddsTheWidgetsAndTheStyles()
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build('<style>p { font-weight: bold }</style><p id="p">Hello</p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $screen = new ScreenBuffer(40, 5);
        $screen->write($terminal->getOutput());
        $this->assertSame($view->getBody()->all()[0], $tui->getById('p'));
        $this->assertStringContainsString("\x1b[1mHello", $screen->getStyledScreen());
    }

    public function testTheTitleOfTheDocumentIsTheOneOfTheTerminal()
    {
        $terminal = new VirtualTerminal(40, 5);
        $view = (new HtmlBuilder())->build("<!DOCTYPE html><html><head><title>  Release\n notes \x1b[2J</title></head><body><p>x</p></body></html>");
        $view->mount(new Tui(null, $terminal));
        $this->assertSame("Release notes \x1b[2J", $view->getDocumentTitle());
        $this->assertStringContainsString("\x1b]0;Release notes [2J\x07", $terminal->getOutput(), 'Control characters are stripped.');
    }

    public function testTheTitleOfTheTerminalBeforeIsPutBackWhenTheViewIsUnmounted()
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build('<title>Page</title><p>x</p>')->mount($tui);
        $this->assertStringContainsString("\x1b[22;0t\x1b]0;Page\x07", $terminal->consumeOutput(), 'Saved, then set.');
        $view->unmount();
        $output = $terminal->consumeOutput();
        $this->assertStringContainsString("\x1b]0;\x07\x1b[23;0t", $output, 'Cleared, then put back where the terminal keeps it.');
    }

    public function testAViewMountedInAContainerKeepsTheTitleOfTheTerminal()
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(null, $terminal);
        $tui->add($container = new ContainerWidget());
        (new HtmlBuilder())->build('<title>Part</title><p>x</p>')->mount($tui, $container);
        $this->assertStringNotContainsString("\x1b]0;", $terminal->getOutput());
    }

    public function testHiddenContentTakesNoFocus()
    {
        [$tui] = $this->mount(<<<'HTML'
            <style>.skipped { content-visibility: hidden; border: 1px solid }</style>
            <div class="skipped"><input id="a" aria-label="a"></div>
            <input id="b" aria-label="b">
            <input id="c" aria-label="c">
            HTML);
        $tui->start();
        $tui->processRender();
        $this->assertNull($tui->getFocus(), 'Like browsers, nothing has the focus before the first Tab.');
        $order = [];
        for ($i = 0; $i < 3; ++$i) {
            $tui->handleInput("\x1b[17~");
            $order[] = $tui->getFocus()->getId();
        }
        $this->assertSame(['b', 'c', 'b'], $order, 'The first widget focused, and the cycle, skip what is inside an element whose content is hidden.');
    }

    public function testTheFirstFocusSkipsWhatCssHides()
    {
        [$tui] = $this->mount(<<<'HTML'
            <style>.gone { display: none } .invisible { visibility: hidden }</style>
            <input id="a" aria-label="a" class="gone">
            <div class="invisible"><input id="b" aria-label="b"></div>
            <input id="c" aria-label="c">
            HTML);
        $tui->start();
        $tui->processRender();
        $tui->handleInput("\x1b[17~");
        $this->assertSame('c', $tui->getFocus()->getId(), 'The first Tab skips what CSS hides.');
    }

    public function testFocusOrderFollowsTabIndex()
    {
        [$tui, $view] = $this->mount(<<<'HTML'
            <input id="a" aria-label="a">
            <input id="b" aria-label="b" tabindex="2">
            <input id="c" aria-label="c" tabindex="-1">
            <input id="d" aria-label="d" tabindex="1">
            <input id="e" aria-label="e" disabled>
            <input id="f" aria-label="f" tabindex="0">
            HTML);
        $this->assertNull($tui->getFocus(), 'Without autofocus, nothing has the focus, like browsers.');
        $order = [];
        $tui->handleInput("\x1b[17~");
        $this->assertSame('d', $tui->getFocus()->getId(), 'The first Tab focuses the first widget of the cycle: the lowest positive tabindex.');
        for ($i = 0; $i < 5; ++$i) {
            $tui->handleInput("\x1b[17~");
            $order[] = $tui->getFocus()->getId();
        }
        $this->assertSame(['b', 'a', 'f', 'd', 'b'], $order, 'Positive tab indexes first, then document order; disabled and negative ones are skipped.');
        $tui->setFocus($view->getById('c'));
        $this->assertSame('c', $tui->getFocus()->getId(), 'A negative tabindex can still be focused programmatically.');
    }

    public function testAutofocus()
    {
        [$tui] = $this->mount('<input id="a" aria-label="a"><input id="b" aria-label="b" autofocus><input id="c" aria-label="c" autofocus>');
        $this->assertSame('b', $tui->getFocus()->getId(), 'The first autofocus wins.');
    }

    public function testAccessKey()
    {
        [$tui, $view] = $this->mount('<input id="a" aria-label="a"><input id="b" aria-label="b" accesskey="S">');
        $this->assertNull($tui->getFocus());
        $tui->handleInput("\x1bs");
        $this->assertSame('b', $tui->getFocus()->getId());
        $this->assertSame('', $view->getById('b')->getValue(), 'The key does not reach the input.');
        $tui->handleInput("\x1bx");
        $this->assertSame('b', $tui->getFocus()->getId());
    }

    public function testDuplicateAccessKeyIsReported()
    {
        $logger = new CollectingLogger();
        $view = (new HtmlBuilder(new CssStyleSheet(null, null, false, new \Symfony\Component\TuiHtml\Css\DeclarationMapper(null, false), new \Symfony\Component\TuiHtml\Widget\Text\HyphenatorRegistry()), null, $logger))->build('<input id="a" aria-label="a" accesskey="s"><input id="b" aria-label="b" accesskey="s">');
        $this->assertTrue($logger->has('The access key "s" is used twice.'));
        $tui = new Tui(null, new VirtualTerminal(40, 5));
        $view->mount($tui);
        $tui->setFocus($view->getById('b'));
        $tui->handleInput("\x1bs");
        $this->assertSame('a', $tui->getFocus()->getId(), 'The first element keeps the key.');
    }

    public function testGetAutofocus()
    {
        $view = (new HtmlBuilder())->build('<input id="a" aria-label="a" autofocus>');
        $this->assertInstanceOf(InputWidget::class, $view->getAutofocus());
        $this->assertNull((new HtmlBuilder())->build('<p>a</p>')->getAutofocus());
    }

    private function mount(string $html): array
    {
        $tui = new Tui(null, new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build($html);
        $view->mount($tui);

        return [$tui, $view];
    }
}
