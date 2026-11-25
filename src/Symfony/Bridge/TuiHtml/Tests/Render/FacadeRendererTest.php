<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Widget\HtmlRootWidget;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class FacadeRendererTest extends TestCase
{
    public function testDefaultBuilderIsUnchangedAndOptInPreservesNativeBodyIdentity(): void
    {
        self::assertNotInstanceOf(HtmlRootWidget::class, new HtmlBuilder()->build('<p>Hello</p>')->getRoot());
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p>Hello</p>');
        self::assertInstanceOf(HtmlRootWidget::class, $view->getRoot());
        self::assertSame($view->getRoot(), $view->getBody()->getParent());
        self::assertNotSame($view->getBody(), $view->getRoot());
    }

    public function testOnlyHtmlRootUsesCoreAndNativeSiblingKeepsItsNaturalRow(): void
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p" style="margin:0">Hello</p>');
        $paragraph = $view->getById('p');
        $outer = new ContainerWidget();
        $before = new TextWidget('BEFORE');
        $after = new TextWidget('AFTER');
        $outer->add($before);
        $outer->add($view->getRoot());
        $outer->add($after);
        $renderer = new FacadeRenderer(new Renderer());
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), $renderer->renderFrame($outer, 30, 8)->toArray());
        self::assertSame(['BEFORE', 'Hello', 'AFTER'], $lines);
        self::assertNotNull($view->getRoot()->getPresentation()->getResult());
        self::assertSame(1, $renderer->getWidgetRect($paragraph)->row);
        self::assertSame(2, $renderer->getWidgetRect($after)->row);
        $paragraph->setText('Changed');
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), $renderer->renderFrame($outer, 30, 8)->toArray());
        self::assertSame(['BEFORE', 'Changed', 'AFTER'], $lines);
        self::assertSame($paragraph, $view->getById('p'));
    }

    public function testMountedFacadeKeepsRealNativeFocusInputAndMutationCallbacks(): void
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<style>p{margin:0}input{width:10ch}</style><input id="field"><p id="message">initial</p>');
        $field = $view->getById('field');
        $message = $view->getById('message');
        $changes = [];
        $field->onChange(static function (ChangeEvent $event) use (&$changes, $message): void {
            $changes[] = [$event->getTarget(), $event->getValue()];
            $message->setText('changed '.$event->getValue());
        });
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(null, $terminal);
        $renderer = new FacadeRenderer(new Renderer());
        $tui->setRenderer($renderer);
        $host = new ContainerWidget();
        $tui->add(new TextWidget('HEADER'));
        $tui->add($host);
        $view->mount($tui, $host);
        $tui->add(new TextWidget('FOOTER'));
        $tui->start();
        try {
            $tui->processRender();
            self::assertSame($view->getBody(), $field->getParent());
            self::assertNotNull($field->getContext());
            $tui->setFocus($field);
            $tui->handleInput('x');
            $tui->processRender();
            self::assertSame($field, $tui->getFocus());
            self::assertSame('x', $field->getValue());
            self::assertSame([[$field, 'x']], $changes);
            self::assertSame('changed x', $message->getText());
            self::assertNotNull($tui->getWidgetRect($field));
            self::assertSame(1, $tui->getWidgetRect($field)->row);
            self::assertStringContainsString('FOOTER', AnsiUtils::stripAnsiCodes($terminal->getOutput()));
            $view->unmount();
            self::assertNull($field->getContext());
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testPreparedSelectRetainsNativeFieldListAndSelectionWithoutDuplicateChrome(): void
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<style>select::picker-icon{content:"@"}</style><select id="choice"><option>A</option><option>B</option></select>');
        $select = $view->getById('choice');
        $field = $select->getField();
        $list = $select->getList();
        $renderer = new FacadeRenderer(new Renderer());
        $tui = new Tui(null, new VirtualTerminal(30, 8));
        $tui->setRenderer($renderer);
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $initial = AnsiUtils::stripAnsiCodes(implode("\n", $renderer->renderFrame($view->getRoot(), 30, 8)->toArray()));
            self::assertSame(1, substr_count($initial, '@'));
            $tui->setFocus($field);
            $tui->handleInput("\x1b[B");
            self::assertTrue($select->isOpen());
            self::assertSame($list, $tui->getFocus());
            $tui->handleInput("\x1b[B");
            $tui->handleInput("\r");
            $tui->processRender();
            self::assertFalse($select->isOpen());
            self::assertSame($field, $tui->getFocus());
            self::assertSame(1, $list->getSelectedIndex());
            self::assertSame($field, $select->getField());
            self::assertSame($list, $select->getList());
            $chosen = AnsiUtils::stripAnsiCodes(implode("\n", $renderer->renderFrame($view->getRoot(), 30, 8)->toArray()));
            self::assertStringContainsString('B', $chosen);
            self::assertSame(1, substr_count($chosen, '@'));
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testUnmountReleasesCapturedRootAndCanRemountSameFacade(): void
    {
        $builder = new HtmlBuilder()->useHtmlRenderer();
        $view = $builder->build('<p style="margin:0">Hello</p>');
        $root = $view->getRoot();
        $reference = \WeakReference::create($root);
        $tui = new Tui(null, new VirtualTerminal(30, 8));
        $tui->setRenderer(new FacadeRenderer(new Renderer()));
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $view->unmount();
            self::assertNull($root->getContext());
            $view->mount($tui);
            $tui->processRender();
            self::assertNotNull($root->getPresentation()->getResult());
            $view->unmount();
        } finally {
            $view->unmount();
            $tui->stop();
        }
        unset($view, $root);
        gc_collect_cycles();
        self::assertNull($reference->get(), 'An externally retained builder must not retain an unmounted root through a captured style lease.');
        self::assertNotNull($builder->getStyleSheet());
    }
}
