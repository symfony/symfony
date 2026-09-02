<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Event\CollapseEvent;
use Symfony\Component\Tui\Event\ExpandEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\CollapsibleWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\WidgetContext;

final class CollapsibleWidgetTest extends TestCase
{
    public function testDefaultStateIsCollapsed()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));

        $this->assertFalse($widget->isExpanded());
    }

    public function testCanBeConstructedExpanded()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'), expanded: true);

        $this->assertTrue($widget->isExpanded());
    }

    public function testGetSummaryReturnsString()
    {
        $widget = new CollapsibleWidget('My Title', new TextWidget('Content'));

        $this->assertSame('My Title', $widget->getSummary());
    }

    public function testNoDescriptionByDefault()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));

        $this->assertNull($widget->getDescription());
    }

    public function testDescriptionCanBeSetViaConstructor()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'), description: '[3]');

        $this->assertSame('[3]', $widget->getDescription());
    }

    public function testAllIsEmptyWhenCollapsed()
    {
        $content = new TextWidget('Content');
        $widget = new CollapsibleWidget('Title', $content);

        $this->assertSame([], $widget->all());
    }

    public function testContentInAllWhenExpanded()
    {
        $content = new TextWidget('Content');
        $widget = new CollapsibleWidget('Title', $content, expanded: true);

        $this->assertSame([$content], $widget->all());
    }

    public function testContentParentIsSetWhenExpanded()
    {
        $content = new TextWidget('Content');
        $widget = new CollapsibleWidget('Title', $content, expanded: true);

        $this->assertSame($widget, $content->getParent());
    }

    public function testToggleExpandsAndCollapses()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));

        $widget->toggle();
        $this->assertTrue($widget->isExpanded());

        $widget->toggle();
        $this->assertFalse($widget->isExpanded());
    }

    public function testToggleWiresAndReleasesTheContent()
    {
        $content = new TextWidget('Content');
        $widget = new CollapsibleWidget('Title', $content);

        $widget->toggle();
        $this->assertSame($widget, $content->getParent());

        $widget->toggle();
        $this->assertNull($content->getParent());
    }

    public function testExpandIsIdempotent()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));

        $widget->expand();
        $widget->expand();

        $this->assertTrue($widget->isExpanded());
    }

    public function testCollapseIsIdempotent()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'), expanded: true);

        $widget->collapse();
        $widget->collapse();

        $this->assertFalse($widget->isExpanded());
    }

    public function testSetSummaryIsFluentInterface()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));

        $this->assertSame($widget, $widget->setSummary('New Title'));
        $this->assertSame('New Title', $widget->getSummary());
    }

    public function testSetDescriptionIsFluentInterface()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));

        $this->assertSame($widget, $widget->setDescription('[5]'));
        $this->assertSame('[5]', $widget->getDescription());
    }

    public function testSetDescriptionCanClearIt()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'), description: '[3]');

        $widget->setDescription(null);

        $this->assertNull($widget->getDescription());
    }

    public function testDispatchesExpandEvent()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));
        $called = false;

        $widget->on(ExpandEvent::class, function (ExpandEvent $event) use (&$called, $widget) {
            $called = true;
            $this->assertSame($widget, $event->getTarget());
        });

        $widget->toggle();

        $this->assertTrue($called);
    }

    public function testDispatchesCollapseEvent()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'), expanded: true);
        $called = false;

        $widget->on(CollapseEvent::class, static function () use (&$called) {
            $called = true;
        });

        $widget->toggle();

        $this->assertTrue($called);
    }

    public function testExpandDoesNotDispatchEventWhenAlreadyExpanded()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'), expanded: true);
        $called = false;

        $widget->on(ExpandEvent::class, static function () use (&$called) {
            $called = true;
        });

        $widget->expand();

        $this->assertFalse($called);
    }

    public function testCollapseDoesNotDispatchEventWhenAlreadyCollapsed()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));
        $called = false;

        $widget->on(CollapseEvent::class, static function () use (&$called) {
            $called = true;
        });

        $widget->collapse();

        $this->assertFalse($called);
    }

    public function testSetContentReplacesContent()
    {
        $content1 = new TextWidget('First');
        $content2 = new TextWidget('Second');
        $widget = new CollapsibleWidget('Title', $content1, expanded: true);

        $widget->setContent($content2);

        $this->assertSame([$content2], $widget->all());
        $this->assertNull($content1->getParent());
        $this->assertSame($widget, $content2->getParent());
    }

    public function testSetContentIsFluentInterface()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('First'));

        $result = $widget->setContent(new TextWidget('Second'));

        $this->assertSame($widget, $result);
    }

    public function testSetContentWhileCollapsedAppearsAfterExpand()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Old'));
        $context = $this->attachWithTui($widget);

        $widget->setContent(new TextWidget('New content'));
        $widget->expand();

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $plain = AnsiUtils::stripAnsiCodes(implode("\n", $lines));

        $this->assertStringContainsString('New content', $plain);
        $this->assertStringNotContainsString('Old', $plain);
    }

    public function testSetContentWhileCollapsedKeepsTheReplacedContentListeners()
    {
        $old = new TextWidget('Old');
        $widget = new CollapsibleWidget('Title', $old);
        $this->attachWithTui($widget);
        $old->on(ChangeEvent::class, static fn () => null);

        $widget->setContent(new TextWidget('New'));

        $this->assertTrue($old->hasListeners(ChangeEvent::class));
    }

    public function testSetSummaryUpdatesRender()
    {
        $widget = new CollapsibleWidget('Old Title', new TextWidget('Content'));
        $context = $this->attachWithTui($widget);

        $widget->setSummary('New Title');

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $firstLine = AnsiUtils::stripAnsiCodes($lines[0] ?? '');

        $this->assertStringContainsString('New Title', $firstLine);
        $this->assertStringNotContainsString('Old Title', $firstLine);
    }

    public function testSymbolPrefixedOnFirstSummaryLine()
    {
        $widget = new CollapsibleWidget('My Title', new TextWidget('Content'));
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $firstLine = AnsiUtils::stripAnsiCodes($lines[0] ?? '');

        $this->assertStringStartsWith('▶ My Title', $firstLine);

        $widget->toggle();

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $firstLine = AnsiUtils::stripAnsiCodes($lines[0] ?? '');

        $this->assertStringStartsWith('▼ My Title', $firstLine);
    }

    public function testCustomSymbols()
    {
        $widget = new CollapsibleWidget(
            summary: 'Title',
            content: new TextWidget('Content'),
            collapsedSymbol: '+',
            expandedSymbol: '-',
        );
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $this->assertStringStartsWith('+ Title', AnsiUtils::stripAnsiCodes($lines[0] ?? ''));

        $widget->toggle();

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $this->assertStringStartsWith('- Title', AnsiUtils::stripAnsiCodes($lines[0] ?? ''));
    }

    public function testDescriptionIsRightAligned()
    {
        $widget = new CollapsibleWidget('Settings', new TextWidget('Content'), description: '[3]');
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $firstLine = AnsiUtils::stripAnsiCodes($lines[0] ?? '');

        $this->assertMatchesRegularExpression('/^▶ Settings\s+\[3\]$/', $firstLine);
        $this->assertSame(40, AnsiUtils::visibleWidth($firstLine));
    }

    public function testDescriptionWrapsToNextLineWhenNoSpace()
    {
        $widget = new CollapsibleWidget('VeryLongSummary', new TextWidget('Content'), description: '[desc]');
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(20, 10));

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('[desc]', AnsiUtils::stripAnsiCodes($lines[1]));
    }

    public function testLongSummaryIsTruncatedToTheAvailableWidth()
    {
        $widget = new CollapsibleWidget(str_repeat('Long summary ', 5), new TextWidget('Content'));
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(20, 10));

        $this->assertLessThanOrEqual(20, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testWrappedDescriptionIsTruncatedToTheAvailableWidth()
    {
        $widget = new CollapsibleWidget('Summary', new TextWidget('Content'), description: str_repeat('description ', 5));
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(20, 10));

        $this->assertCount(2, $lines);
        $this->assertLessThanOrEqual(20, AnsiUtils::visibleWidth($lines[1]));
    }

    public function testContentRenderedWhenExpanded()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Hello content'));
        $context = $this->attachWithTui($widget);

        $widget->toggle();

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $plain = AnsiUtils::stripAnsiCodes(implode("\n", $lines));

        $this->assertStringContainsString('Hello content', $plain);
    }

    public function testContentNotRenderedWhenCollapsed()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Hidden content'));
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));
        $plain = AnsiUtils::stripAnsiCodes(implode("\n", $lines));

        $this->assertStringNotContainsString('Hidden content', $plain);
    }

    public function testNestedCollapsiblesRender()
    {
        $inner = new CollapsibleWidget('Inner', new TextWidget('Deep content'), expanded: true);
        $outer = new CollapsibleWidget('Outer', $inner, expanded: true);
        $context = $this->attachWithTui($outer);

        $lines = $context->renderWidget($outer, new RenderContext(40, 10));
        $plain = AnsiUtils::stripAnsiCodes(implode("\n", $lines));

        $this->assertStringContainsString('Outer', $plain);
        $this->assertStringContainsString('Inner', $plain);
        $this->assertStringContainsString('Deep content', $plain);
    }

    public function testExpandedContentFitsTheRowBudget()
    {
        $editor = (new EditorWidget())->expandVertically(true);
        $editor->setText(implode("\n", array_map(static fn (int $i) => "line $i", range(1, 20))));
        $widget = new CollapsibleWidget('Title', $editor, expanded: true);
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(40, 10));

        $this->assertCount(10, $lines);
        $this->assertStringContainsString('line 1', AnsiUtils::stripAnsiCodes(implode("\n", $lines)));
    }

    public function testExpandedContentFitsTheRowBudgetWithAWrappedDescription()
    {
        $editor = (new EditorWidget())->expandVertically(true);
        $editor->setText(implode("\n", array_map(static fn (int $i) => "line $i", range(1, 20))));
        $widget = new CollapsibleWidget('VeryLongSummary', $editor, expanded: true, description: '[desc]');
        $context = $this->attachWithTui($widget);

        $lines = $context->renderWidget($widget, new RenderContext(20, 10));

        $this->assertStringContainsString('[desc]', AnsiUtils::stripAnsiCodes($lines[1]));
        $this->assertCount(10, $lines);
    }

    public function testKeybindingsToggleExpandAndCollapse()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));
        $this->attachWithTui($widget);

        $widget->handleInput("\r");
        $this->assertTrue($widget->isExpanded());

        $widget->handleInput("\r");
        $this->assertFalse($widget->isExpanded());

        $widget->handleInput("\e[C");
        $this->assertTrue($widget->isExpanded());

        $widget->handleInput("\e[D");
        $this->assertFalse($widget->isExpanded());
    }

    public function testFocusedHeaderIsReversedByDefault()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));
        $context = $this->attachWithTui($widget);

        $widget->setFocused(true);
        $lines = $context->renderWidget($widget, new RenderContext(40, 10));

        $this->assertStringContainsString("\e[7m", $lines[0]);
    }

    public function testFocusStyleIsDrivenByTheStylesheet()
    {
        $widget = new CollapsibleWidget('Title', new TextWidget('Content'));
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $tui->addStyleSheet(new StyleSheet([
            CollapsibleWidget::class.'::summary:focus' => new Style(underline: true),
        ]));
        $tui->add($widget);

        $widget->setFocused(true);
        $lines = $widget->getContext()->renderWidget($widget, new RenderContext(40, 10));

        $this->assertStringContainsString("\e[4m", $lines[0]);
    }

    public function testCollapsingReturnsFocusToTheHeader()
    {
        $input = new InputWidget();
        $widget = new CollapsibleWidget('Title', $input, expanded: true);
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($widget);

        $tui->setFocus($input);
        $this->assertTrue($input->isFocused());

        $widget->collapse();

        $this->assertSame($widget, $tui->getFocus());
        $this->assertTrue($widget->isFocused());
        $this->assertFalse($input->isFocused());
    }

    public function testCollapsedContentIsNotFocusReachable()
    {
        $input = new InputWidget();
        $widget = new CollapsibleWidget('Title', $input, expanded: true);
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($widget);
        $focusManager = $tui->getFocusManager();

        $this->assertContains($input, $focusManager->all());

        $widget->collapse();

        $this->assertNotContains($input, $focusManager->all());
        $focusManager->focusNext();
        $this->assertSame($widget, $tui->getFocus());

        $widget->expand();

        $focusManager->focusNext();
        $this->assertSame($input, $tui->getFocus());
    }

    private function attachWithTui(CollapsibleWidget $widget): WidgetContext
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $tui->add($widget);

        return $widget->getContext();
    }
}
