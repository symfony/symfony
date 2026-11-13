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
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;

final class SmoothScrollTest extends TestCase
{
    private float $now = 100.0;

    private ?Tui $tui = null;
    private VirtualTerminal $terminal;
    private ScreenBuffer $buffer;

    protected function tearDown(): void
    {
        $this->tui?->stop();
    }

    public function testTheContentGlides()
    {
        [$tui, $view, $renderer] = $this->mount(new MediaContext());
        $box = $view->getById('box');
        $this->assertInstanceOf(ContainerWidget::class, $box);

        $view->navigate('#end');
        $tui->processRender();
        $this->assertSame(20, $box->getScrollTop(), 'Where it is scrolled to, at once.');
        $this->assertTrue($renderer->isScrolling());
        $this->assertSame(0, $this->shown($view), 'The rows shown still start at the top.');

        $this->now += 0.075;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(15, $this->shown($view), 'Halfway in time, eased: three quarters of the way.');

        $this->now += 0.1;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(20, $this->shown($view));
        $this->assertFalse($renderer->isScrolling(), 'There: no more frames.');
    }

    public function testTheContentGlidesSideways()
    {
        $this->terminal = new VirtualTerminal(40, 10);
        $this->buffer = new ScreenBuffer(40, 10);
        $tui = $this->tui = new Tui(terminal: $this->terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div id="box" style="overflow-x: auto; width: 10ch; scroll-behavior: smooth"><pre>'.implode('', range('a', 'z')).'abcd</pre></div>')->mount($tui);
        $renderer = (fn (): Renderer => $this->renderer)->call($tui);
        $renderer->setClock(fn (): float => $this->now);
        $tui->start();
        $tui->processRender();
        $box = $view->getById('box');
        $first = function (): string {
            $this->buffer->write($this->terminal->consumeOutput());

            return explode("\n", $this->buffer->getScreen())[0][0];
        };

        $box->scrollToColumn(20);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(20, $box->getScrollLeft());
        $this->assertTrue($renderer->isScrolling());
        $this->assertSame('a', $first(), 'The columns shown still start at the left.');

        $this->now += 0.075;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('p', $first(), 'Halfway in time, eased: three quarters of the way, column 15.');

        $this->now += 0.1;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('u', $first());
        $this->assertFalse($renderer->isScrolling());
    }

    public function testReducedMotionScrollsAtOnce()
    {
        [$tui, $view, $renderer] = $this->mount((new MediaContext())->withReducedMotion());

        $view->navigate('#end');
        $tui->processRender();
        $this->assertSame(20, $this->shown($view));
        $this->assertFalse($renderer->isScrolling());
    }

    /**
     * @return array{Tui, HtmlView, Renderer}
     */
    private function mount(MediaContext $media): array
    {
        $this->terminal = new VirtualTerminal(40, 10);
        $this->buffer = new ScreenBuffer(40, 10);
        $tui = $this->tui = new Tui(terminal: $this->terminal);
        $tui->setMediaContext($media);
        $lines = implode('', array_map(static fn (int $i) => "<p>line $i</p>", range(1, 20)));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>#box { overflow: auto; max-height: 4; scroll-behavior: smooth } p, h2 { margin: 0 }</style><div id="box">'.$lines.'<h2 id="end">The end</h2>'.$lines.'</div>')->mount($tui);
        $renderer = (fn (): Renderer => $this->renderer)->call($tui);
        $renderer->setClock(fn (): float => $this->now);
        $tui->start();
        $tui->processRender();

        return [$tui, $view, $renderer];
    }

    /**
     * The row of the content drawn at the top of the box: line N is the row N - 1, the heading the row 20.
     */
    private function shown(HtmlView $view): int
    {
        $this->buffer->write($this->terminal->consumeOutput());
        $first = trim(explode("\n", $this->buffer->getScreen())[0], ' │┃');

        return 'The end' === $first ? 20 : (int) substr($first, 5) - 1;
    }
}
