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
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * An application going from page to page, each built and mounted on the same Tui, the previous one unmounted, does not
 * keep the pages it left.
 */
final class PageRetentionTest extends TestCase
{
    private const PAGE_A = <<<'HTML'
        <!DOCTYPE html>
        <html lang="en"><head><title>Page A</title><style>
            .card:hover .label { display: inline } .label { display: none } a:visited { color: red } .r { fill: red }
            @media (min-width: 60ch) { .wide { display: inline } }
        </style></head>
        <body>
            <h1 id="top">Page A</h1>
            <p>Some <b>text</b> and <a href="#top">a link</a>.</p>
            <a class="card" href="/b" style="display: block">a <span>block</span> link</a>
            <div class="card"><button accesskey="s">Save <span class="label">now</span><span class="wide">!</span></button></div>
            <label for="name">Name</label> <input id="name" tabindex="2" autofocus>
            <dialog id="d"><form method="dialog"><button value="ok">OK</button></form></dialog>
            <div popover id="p">pop</div>
            <p>An icon: <svg width="4" height="2" viewBox="0 0 4 2"><rect class="r" width="4" height="2"/></svg></p>
            <button><svg width="2" height="1" aria-label="Close"><rect width="2" height="1"/></svg></button>
        </body></html>
        HTML;

    private const PAGE_B = '<!DOCTYPE html><html><head><style>p { color: blue }</style></head><body><p>Page <i>B</i></p><button>Back</button></body></html>';

    public function testAPageLeftIsNotKept()
    {
        $builder = new HtmlBuilder(new CssStyleSheet());
        $terminal = new VirtualTerminal(80, 12);
        $tui = new Tui(terminal: $terminal);
        $tui->start();

        $refs = $this->visitPageA($builder, $tui);

        $builder->build(self::PAGE_B)->mount($tui);
        $tui->processRender();
        $buffer = new ScreenBuffer(80, 12);
        $buffer->write($terminal->consumeOutput());
        $this->assertStringContainsString('Page B', $buffer->getScreen());
        $this->assertStringNotContainsString('Page A', $buffer->getScreen());

        $this->assertCollected($refs);
    }

    public function testThePageOfTheWholeScreenMountedReplacesTheOneBefore()
    {
        $builder = new HtmlBuilder(new CssStyleSheet());
        $terminal = new VirtualTerminal(80, 12);
        $tui = new Tui(terminal: $terminal);
        $tui->start();

        // Not unmounted: the next page of the whole screen unmounts it, like a browser going to another page
        $refs = $this->visitPageA($builder, $tui, false);

        $builder->build(self::PAGE_B)->mount($tui);
        $tui->processRender();
        $buffer = new ScreenBuffer(80, 12);
        $buffer->write($terminal->consumeOutput());
        $this->assertStringContainsString('Page B', $buffer->getScreen());
        $this->assertStringNotContainsString('Page A', $buffer->getScreen());

        $this->assertCollected($refs);
    }

    public function testAViewMountedInAPartOfThePageReplacedGoesWithIt()
    {
        $builder = new HtmlBuilder(new CssStyleSheet());
        $tui = new Tui(terminal: new VirtualTerminal(80, 12));
        $tui->start();

        $page = $builder->build('<h1>Page A</h1><div id="panel"></div>');
        $page->mount($tui);
        $panel = $page->getById('panel');
        $this->assertInstanceOf(ContainerWidget::class, $panel);
        $part = $builder->build('<p>A part of it</p>')->mount($tui, $panel);
        $tui->processRender();
        $ref = \WeakReference::create($part);
        unset($page, $panel, $part);

        $builder->build(self::PAGE_B)->mount($tui);
        $tui->processRender();
        while (gc_collect_cycles() > 0) {
        }
        $this->assertNull($ref->get(), 'The view mounted in a part of the page left is not kept.');
    }

    public function testAPageLeftIsNotKeptWithAStyleSheetPerPage()
    {
        $terminal = new VirtualTerminal(80, 12);
        $tui = new Tui(terminal: $terminal);
        $tui->start();

        $refs = $this->visitPageA(new HtmlBuilder(new CssStyleSheet()), $tui);

        new HtmlBuilder(new CssStyleSheet())->build(self::PAGE_B)->mount($tui);
        $tui->processRender();

        $this->assertCollected($refs);
    }

    public function testAPageUnmountedIsNotKeptBeforeTheNextOne()
    {
        $builder = new HtmlBuilder(new CssStyleSheet());
        $tui = new Tui(terminal: new VirtualTerminal(80, 12));
        $tui->start();

        $refs = $this->visitPageA($builder, $tui);
        $tui->processRender();

        $this->assertCollected($refs);
    }

    public function testAViewCanBeMountedAgain()
    {
        $terminal = new VirtualTerminal(40, 6);
        $tui = new Tui(terminal: $terminal);
        $tui->start();
        $builder = new HtmlBuilder(new CssStyleSheet());
        $a = $builder->build('<style>p { margin: 0 }</style><p>Page A</p>')->mount($tui);
        $tui->processRender();
        $a->unmount();
        $b = $builder->build('<p>Page B</p>')->mount($tui);
        $tui->processRender();
        $b->unmount();
        $a->mount($tui);
        $tui->processRender();

        $buffer = new ScreenBuffer(40, 6);
        $buffer->write($terminal->consumeOutput());
        $this->assertStringContainsString('Page A', $buffer->getScreen());
        $this->assertStringNotContainsString('Page B', $buffer->getScreen());
    }

    public function testAViewLeftAfterTheNextOneIsMountedKeepsTheStylesTheyShare()
    {
        $terminal = new VirtualTerminal(40, 6);
        $tui = new Tui(terminal: $terminal);
        $tui->start();
        $builder = new HtmlBuilder(new CssStyleSheet());
        $a = $builder->build('<style>p { margin: 0 }</style><p>Page A</p>')->mount($tui);
        $tui->processRender();
        $builder->build('<style>.b { text-transform: uppercase }</style><p class="b">Page B</p>')->mount($tui);
        $a->unmount();
        $tui->processRender();

        $buffer = new ScreenBuffer(40, 6);
        $buffer->write($terminal->consumeOutput());
        $this->assertStringContainsString('PAGE B', $buffer->getScreen());
        $this->assertStringNotContainsString('Page A', $buffer->getScreen());
    }

    /**
     * Builds page A, mounts it, uses it a little, and unmounts it; nothing of it is held out of here.
     *
     * @return array<string, \WeakReference<object>>
     */
    private function visitPageA(HtmlBuilder $builder, Tui $tui, bool $unmount = true): array
    {
        $refs = [];
        $registry = $builder->getRegistry();
        foreach (['a', 'p', 'div', 'button', 'input', 'h1', 'label', 'dialog', 'form'] as $tag) {
            if (null === $factory = $registry->get($tag)) {
                continue;
            }
            $registry->register($tag, static function (\Dom\Element $element, HtmlBuilder $builder) use ($factory, &$refs, $tag) {
                $refs['document'] ??= \WeakReference::create($element->ownerDocument);
                $refs['<'.$tag.'> '.\count($refs)] = \WeakReference::create($element);

                return $factory->create($element, $builder);
            });
        }

        $view = $builder->build(self::PAGE_A);
        $view->mount($tui);
        $tui->processRender();

        // Used a little: a pointer over the card, a link followed, a popover shown, the focus moved
        $view->hover(...$this->cellOf($tui, $view, '.card button'));
        $tui->processRender();
        $view->click(...$this->cellOf($tui, $view, 'a.card'));
        $tui->processRender();
        $view->showPopover($view->getById('p'));
        $tui->processRender();
        $view->hidePopover($view->getById('p'));
        $tui->handleInput("\t");
        $tui->processRender();

        $refs['view'] = \WeakReference::create($view);
        $refs['root'] = \WeakReference::create($view->getRoot());
        $refs['button'] = \WeakReference::create($view->querySelector('button'));
        $refs['styled'] = \WeakReference::create($view->querySelector('.card'));

        if ($unmount) {
            $view->unmount();
        }

        return $refs;
    }

    /**
     * @return array{int, int}
     */
    private function cellOf(Tui $tui, HtmlView $view, string $selector): array
    {
        $rect = $tui->getWidgetRect($view->querySelector($selector));
        $this->assertNotNull($rect);

        return [$rect->row, $rect->col];
    }

    /**
     * @param array<string, \WeakReference<object>> $refs
     */
    private function assertCollected(array $refs): void
    {
        while (gc_collect_cycles()) {
        }
        $kept = array_keys(array_filter($refs, static fn (\WeakReference $ref) => null !== $ref->get()));
        $this->assertSame([], $kept, 'What page A was made of is collected once it is left.');
        $this->assertArrayHasKey('document', $refs);
    }
}
