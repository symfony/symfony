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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Event\LinkEvent;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\LinkedTextWidget;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

final class LinkedTextWidgetTest extends TestCase
{
    private const HTML = '<p id="nav">See <a href="https://symfony.com">the site</a> or <a href="#end">the end</a>.</p><p>No link</p><h2 id="end">End</h2>';

    public function testTextWithLinksTakesTheFocus()
    {
        [$tui, $view] = $this->mount();
        $nav = $view->getById('nav');
        $this->assertInstanceOf(LinkedTextWidget::class, $nav);
        $this->assertNotInstanceOf(LinkedTextWidget::class, $view->querySelector('p:not(#nav)'));
        $this->assertInstanceOf(RichTextWidget::class, $view->querySelector('p:not(#nav)'));
        $this->assertNull($tui->getFocus(), 'Nothing is focused until the user moves the focus, like in a browser.');
        $this->assertSame(['https://symfony.com', '#end'], $nav->getLinks());
        $tui->setFocus($nav);
        $this->assertSame('https://symfony.com', $nav->getCurrentHref(), 'The first link.');
        $this->assertStringContainsString("\x1b[7m", $nav->getText(), 'Reversed.');
        $this->assertStringContainsString("\x1b_pi:c;", $nav->getText(), 'The cursor is on it.');
        $tui->handleInput("\t");
        $this->assertSame('#end', $nav->getCurrentHref(), 'Tab goes to the next one.');
        $tui->handleInput("\t");
        $this->assertSame('#end', $nav->getCurrentHref(), 'The last one stays.');
        $tui->handleInput("\x1b[Z");
        $this->assertSame('https://symfony.com', $nav->getCurrentHref(), 'Shift+Tab goes back.');
        $tui->setFocus(null);
        $this->assertNull($nav->getCurrentHref());
        $this->assertStringNotContainsString("\x1b[7m", $nav->getText());
    }

    public function testEnterFollowsTheLink()
    {
        [$tui, $view] = $this->mount();
        $nav = $view->getById('nav');
        $followed = [];
        $nav->on(LinkEvent::class, static function (LinkEvent $event) use (&$followed): void {
            $followed[] = $event->getHref();
        });
        $tui->setFocus($nav);
        $tui->handleInput("\r");
        $this->assertSame(['https://symfony.com'], $followed, 'The application opens it, if it wants.');
        $this->assertNull($view->getTarget());
        $tui->handleInput("\t");
        $tui->handleInput("\r");
        $this->assertSame($view->getById('end'), $view->getTarget(), 'A fragment: the view goes there.');
    }

    public function testStoppingTheEventKeepsTheViewWhereItIs()
    {
        [$tui, $view] = $this->mount();
        $nav = $view->getById('nav');
        $nav->on(LinkEvent::class, static fn (LinkEvent $event) => $event->stopPropagation());
        $tui->setFocus($nav);
        $tui->handleInput("\t");
        $tui->handleInput("\r");
        $this->assertNull($view->getTarget());
    }

    public function testAControlHiddenOnceStyledLeavesNoLinkCurrent()
    {
        // The skip link of go.dev, shown on focus: the only control of the page is hidden at the first frame
        [$tui, $view] = $this->mount('<style>.skip { position: absolute; clip: rect(0 0 0 0) } .skip:focus { clip: unset } button { display: none }</style><p><a class="skip" href="#main">Skip to main content</a></p><button>Menu</button><p id="main">Main</p>');
        $this->assertNull($tui->getFocus(), 'Like before the first Tab: no link current, the skip link hidden.');
        $this->assertStringNotContainsString('Skip', $tui->getTerminal()->getOutput());
    }

    public function testTheBlocksOfALinkAreThatLinkOneStopOfTab()
    {
        [$tui, $view] = $this->mount('<a id="card" href="#end"><div><h3 id="title">Title</h3><p id="text">Text</p></div>Caption</a><p id="after"><a href="#end">after</a></p><h2 id="end">End</h2>');
        $title = $view->getById('title');
        $text = $view->getById('text');
        $this->assertInstanceOf(LinkedTextWidget::class, $title);
        $this->assertInstanceOf(LinkedTextWidget::class, $text);
        $this->assertSame(['#end'], $title->getLinks());
        $this->assertSame(['#end'], $text->getLinks(), 'Each block is the link, followed where it is clicked.');
        $tui->getFocusManager()->focusNext();
        $this->assertSame($title, $tui->getFocus());
        $tui->getFocusManager()->focusNext();
        $this->assertSame($view->getById('after'), $tui->getFocus(), 'The other blocks of the link are no stop of Tab.');
        $followed = [];
        $text->on(LinkEvent::class, static function (LinkEvent $event) use (&$followed): void {
            $followed[] = $event->getHref();
        });
        $tui->setFocus($text);
        $tui->handleInput("\r");
        $this->assertSame(['#end'], $followed);
    }

    private function mount(string $html = self::HTML): array
    {
        $tui = new Tui(null, new VirtualTerminal(60, 10));
        $view = (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, $view];
    }
}
