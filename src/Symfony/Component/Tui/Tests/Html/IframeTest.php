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
use Symfony\Component\Tui\Html\Element\ImageElement;
use Symfony\Component\Tui\Html\ElementRegistry;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\FrameWidget;
use Symfony\Component\Tui\Widget\InputWidget;

class IframeTest extends TestCase
{
    public function testAnAttachedFrameCanBeMountedAloneAndRemounted()
    {
        $terminal = new VirtualTerminal(30, 8);
        $tui = new Tui(null, $terminal);
        $page = (new HtmlBuilder())->setDefaultStyles(false)->build('<p>outer</p><iframe srcdoc="&lt;style&gt;p { color: #123456 }&lt;/style&gt;&lt;p id=inner&gt;inner&lt;/p&gt;"></iframe>');
        $frame = $page->getFrames()[0];
        $page->mount($tui);
        $tui->start();
        $tui->processRender();
        $frame->mount($tui);
        $tui->processRender();
        $page->unmount();
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#123456', (fn () => $this->renderer->resolveStyle($frame->getById('inner'))->getColor()?->toHex())->call($tui));
        $frame->unmount();
        $frame->mount($tui);
        $tui->processRender();
        $this->assertSame('#123456', (fn () => $this->renderer->resolveStyle($frame->getById('inner'))->getColor()?->toHex())->call($tui));
        $tui->stop();
    }

    public function testTheDocumentOfASrcdocIsNestedWithItsOwnStyles()
    {
        $view = (new HtmlBuilder())->build('<style>input { caret-shape: bar }</style><input id="outer"><iframe title="Form" srcdoc="<style>input { caret-shape: underscore }</style><input id=inner>"></iframe>');
        $this->assertCount(1, $view->getFrames());
        $frame = $view->getFrames()[0];
        $this->assertInstanceOf(FrameWidget::class, $frame->getRoot()->getParent());
        $this->assertSame('Form', $frame->getRoot()->getParent()->getLabel());
        $this->assertNull($view->querySelector('#inner'), 'Another document.');

        $tui = new Tui(terminal: new VirtualTerminal(60, 20));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();

        $outer = $view->querySelector('#outer');
        $inner = $frame->querySelector('#inner');
        $this->assertInstanceOf(InputWidget::class, $inner);

        // Each document styles its own fields only
        $this->assertSame(CursorShape::Bar, $view->getStyleSheet()->getElementStyles($outer, 'cursor')[0]->getCursorShape());
        $this->assertSame(CursorShape::Underline, $frame->getStyleSheet()->getElementStyles($inner, 'cursor')[0]->getCursorShape());
        $this->assertSame([], $view->getStyleSheet()->getElementStyles($inner, 'cursor'), 'The rules of the page do not reach the frame.');
        $this->assertSame([], $view->getStyleSheet()->getStyles($inner));
        $this->assertSame([], $frame->getStyleSheet()->getStyles($outer), 'The rules of the frame do not reach the page.');

        // Its fields join the focus order of the page
        $tui->setFocus($outer);
        $tui->getFocusManager()->focusNext();
        $this->assertSame($inner, $tui->getFocus());
        $tui->stop();
    }

    public function testTheKeysOfAFrameWork()
    {
        $view = (new HtmlBuilder())->build('<input id="outer"><iframe srcdoc="<input id=inner accesskey=k>"></iframe>');
        $tui = new Tui(terminal: new VirtualTerminal(60, 20));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();

        $tui->handleInput("\ek");
        $this->assertSame($view->getFrames()[0]->querySelector('#inner'), $tui->getFocus(), 'Its access keys, like those of the page.');
        $tui->stop();
    }

    public function testFramesNestThreeDeepAtMost()
    {
        $html = '<p>4</p>';
        foreach ([3, 2, 1] as $level) {
            $html = '<p>'.$level.'</p><iframe srcdoc="'.htmlspecialchars($html).'"></iframe>';
        }
        $view = (new HtmlBuilder())->build($html);

        $depth = 0;
        for ($frames = $view->getFrames(); $frames; $frames = $frames[0]->getFrames()) {
            ++$depth;
        }
        $this->assertSame(3, $depth);
    }

    public function testWithoutALoaderASrcIsNotLoaded()
    {
        $view = (new HtmlBuilder())->build('<iframe src="https://example.com" title="Example"></iframe>');
        $this->assertSame([], $view->getFrames());
    }

    public function testTheDocumentOfASrc()
    {
        $documents = ['help.html' => '<p id="help">Help</p>', 'logo.png' => "\x89PNG\xFF\xFE"];
        $registry = (new ElementRegistry())->register('iframe', new ImageElement(static fn (string $src) => $documents[$src] ?? throw new \RuntimeException('Not found.')));
        $view = (new HtmlBuilder(registry: $registry))->build('<iframe src="help.html" title="Help"></iframe><iframe src="data:text/html,%3Cp%20id%3Dinline%3EInline%3C%2Fp%3E"></iframe><iframe src="missing.html" title="Missing"></iframe><iframe src="logo.png" title="Logo"></iframe><iframe srcdoc="<p id=doc>Doc</p>" src="help.html"></iframe>');

        $frames = $view->getFrames();
        $this->assertCount(3, $frames, 'Through the loader, from a data: URL, and srcdoc first.');
        $this->assertNotNull($frames[0]->querySelector('#help'));
        $this->assertNotNull($frames[1]->querySelector('#inline'));
        $this->assertNotNull($frames[2]->querySelector('#doc'));

        $terminal = new VirtualTerminal(80, 30);
        $tui = new Tui(terminal: $terminal);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->getOutput();
        $this->assertStringContainsString('[Missing]', $output, 'A document that cannot be read: its title.');
        $this->assertStringContainsString('[Logo]', $output, 'Nor one that is not text.');
    }
}
