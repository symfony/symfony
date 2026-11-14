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

final class VisitedLinkTest extends TestCase
{
    private const HTML = <<<'HTML'
        <style>a:visited { text-transform: uppercase } a:link { text-decoration: none } .card:visited { text-transform: uppercase }</style>
        <p>See <a href="/docs">docs</a> and <a href="/blog">blog</a></p>
        <a class="card" href="/about" style="display: block">about us</a>
        HTML;

    public function testLinksAreNotVisitedAtFirst()
    {
        [, $view, $screen] = $this->mount();
        $this->assertSame(['See docs and blog', 'about us'], $screen());
        $this->assertSame([], $view->getVisitedLinks());
    }

    public function testTheApplicationSaysWhichLinksAreVisited()
    {
        [$tui, $view, $screen] = $this->mount();
        $view->setVisitedLinks(['/blog', ' /about ', '']);
        $tui->processRender();
        $this->assertSame(['See docs and BLOG', 'ABOUT US'], $screen(), 'Inline links and block ones, by their href.');
        $this->assertSame(['/blog', '/about'], $view->getVisitedLinks());
        $view->setVisitedLinks([]);
        $tui->processRender();
        $this->assertSame(['See docs and blog', 'about us'], $screen(), 'Forgotten: not visited any more.');
    }

    public function testALinkFollowedIsVisited()
    {
        [$tui, $view, $screen] = $this->mount();
        // The text focused, on its first link, which Enter follows
        $tui->setFocus($view->widgetAt(0, 5));
        $tui->handleInput("\r");
        $tui->processRender();
        $this->assertSame(['/docs'], $view->getVisitedLinks());
        $this->assertSame(['See DOCS and blog', 'about us'], $screen());
    }

    public function testALinkFollowedIsVisitedByItsAddressResolvedAgainstTheBase()
    {
        $terminal = new VirtualTerminal(30, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<base href="https://ex.test/dir/"><style>a:visited { text-transform: uppercase }</style><p><a href="page.html">page</a></p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->setFocus($view->widgetAt(0, 1));
        $tui->handleInput("\r");
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 4);
        $buffer->write($terminal->consumeOutput());
        $this->assertSame(['https://ex.test/dir/page.html'], $view->getVisitedLinks());
        $this->assertSame('PAGE', rtrim(explode("\n", $buffer->getScreen())[0]));
    }

    private function mount(): array
    {
        $terminal = new VirtualTerminal(30, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build(self::HTML)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 4);

        return [$tui, $view, static function () use ($terminal, $buffer): array {
            $buffer->write($terminal->consumeOutput());

            return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
        }];
    }
}
