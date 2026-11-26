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
use Symfony\Bridge\TuiHtml\Html\HtmlView;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\ExpandEvent;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class FacadeDetailsTest extends TestCase
{
    public function testNativeToggleFocusAndBodyInstancesSurviveSemanticHeaderProjection()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<details id="details" open><summary>Header</summary><p id="message" style="margin:0">Body unique</p><input id="field"></details>');
        $details = $view->getById('details');
        $message = $view->getById('message');
        $field = $view->getById('field');
        $details->collapse();
        $events = [];
        $details->on(ExpandEvent::class, static function (ExpandEvent $event) use (&$events): void { $events[] = $event->getTarget(); });
        $tui = new Tui(null, new VirtualTerminal(32, 12));
        $tui->setRenderer(new FacadeRenderer(new Renderer()));
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            self::assertSame(1, substr_count($this->text($view), 'Header'));
            self::assertStringNotContainsString('Body unique', $this->text($view));
            $bodyNode = $view->getRoot()->getPresentation()->getResult()->getDocument()->getElementById('message');
            self::assertNotNull($bodyNode);
            self::assertNull($field->getContext());
            $tui->setFocus($details);
            $tui->handleInput(' ');
            $tui->processRender();
            self::assertSame([$details], $events);
            self::assertSame($details, $tui->getFocus());
            self::assertSame($field, $view->getById('field'));
            self::assertNotNull($field->getContext());
            self::assertNotNull($tui->getWidgetRect($field));
            self::assertSame(1, substr_count($this->text($view), 'Body unique'));
            self::assertSame($bodyNode, $view->getRoot()->getPresentation()->getResult()->getDocument()->getElementById('message'));
            $message->setText('Updated body');
            $tui->requestRender();
            $tui->processRender();
            self::assertSame(1, substr_count($this->text($view), 'Updated body'));
            $details->collapse();
            $tui->requestRender();
            $tui->processRender();
            self::assertNull($field->getContext());
            self::assertSame($bodyNode, $view->getRoot()->getPresentation()->getResult()->getDocument()->getElementById('message'));
            self::assertStringNotContainsString('Updated body', $this->text($view));
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testExplicitRenderDetectsNestedClosedBodyChangesAndForgetsRemovedOwners()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<details id="outer" open><summary>Outer</summary><details id="inner" open><summary>Inner</summary><p id="leaf">Leaf</p></details></details>');
        $outer = $view->getById('outer');
        $inner = $view->getById('inner');
        $leaf = $view->getById('leaf');
        $inner->collapse();
        $outer->collapse();
        $tui = new Tui(null, new VirtualTerminal(32, 12));
        $tui->setRenderer(new FacadeRenderer(new Renderer()));
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $node = $view->getRoot()->getPresentation()->getResult()->getDocument()->getElementById('leaf');
            self::assertNotNull($node);
            $leaf->setText('');
            $tui->requestRender();
            $tui->processRender();
            self::assertSame($node, $view->getRoot()->getPresentation()->getResult()->getDocument()->getElementById('leaf'));
            self::assertSame('', $node->textContent);
            $view->getBody()->remove($outer);
            $tui->requestRender();
            $tui->processRender();
            $result = $view->getRoot()->getPresentation()->getResult();
            self::assertNull($result->getDocument()->getElementById('leaf'));
            $leaf->setText('Orphan');
            $tui->requestRender();
            $tui->processRender();
            self::assertSame($result, $view->getRoot()->getPresentation()->getResult());
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testSummaryMarkersColorsAndRightAlignedContentMatchExistingNativePresentation()
    {
        $html = file_get_contents(\dirname(__DIR__).'/Fixtures/Compat/html-summary.html');
        foreach ([[], ['density' => 'condensed']] as $options) {
            self::assertSame(
                TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options), 60, 80, [229, 229, 229], [0, 0, 0]),
                TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, true), 60, 80, [229, 229, 229], [0, 0, 0]),
            );
        }
    }

    private function text(HtmlView $view): string
    {
        return AnsiUtils::stripAnsiCodes(implode("\n", new DisplayListRenderer()->render($view->getRoot()->getPresentation()->getResult()->getDisplayList(), 32, 12)));
    }
}
