<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\LogicException as TuiLogicException;
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\FrameWriterInterface;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer as NativeRenderer;
use Symfony\Component\Tui\Render\RendererDecoratorInterface;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\TuiHtml\Exception\LogicException;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Internal\ContainerState;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Render\Renderer;
use Symfony\Component\TuiHtml\Style\ColorScheme;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

class RendererExtensionTest extends TestCase
{
    public function testMountRequiresExplicitSelection()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $view = (new HtmlBuilder())->build('<p>Hello</p>');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Select the TuiHtml renderer');
        $view->mount($tui);
    }

    public function testAViewMountsThroughADecoratorOfTheRenderer()
    {
        $renderer = new Renderer();
        $tui = new Tui(null, new VirtualTerminal(20, 5), renderer: new DecoratingRenderer(new DecoratingRenderer($renderer)));
        $view = (new HtmlBuilder())->build('<style>p { caret-color: #123456 }</style><p id="text">Hello</p>');
        $view->mount($tui);
        $this->assertContains($view->getStyleSheet(), $renderer->getStyleSheet()->getProviders(), 'The styles of the view go to the decorated renderer.');

        $tui->start();
        try {
            $tui->processRender();
            $text = $view->getById('text');
            $this->assertSame('#123456', StyleBridge::resolve($text->getContext(), $text)?->getCaretColor()?->toHex(), 'Styles are resolved by the decorated renderer, with what only it knows.');
            $this->assertSame($renderer->getMediaContext(), StyleBridge::media($tui));
        } finally {
            $tui->stop();
        }
    }

    public function testThePointerFindsTheLinksWhereADecoratorDrawsTheFrame()
    {
        $tui = new Tui(null, new VirtualTerminal(30, 5), renderer: new DecoratingRenderer(new Renderer(), offset: [2, 3]));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p id="p">Go <a href="#end">down</a> <a href="/x">x</a></p><div style="height: 10"></div><p id="end">End</p>')->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $text = $view->getById('p');
            $this->assertInstanceOf(RichTextWidget::class, $text);
            // "down" in columns 3 to 6 of the first row of the frame, drawn 2 rows lower and 3 columns right
            $view->hover(2, 7);
            $this->assertSame(0, $text->getHoveredLink());
            $this->assertSame('pointer', $view->cursorAt(2, 7));
            $this->assertTrue($view->click(2, 7));
            $this->assertSame($view->getById('end'), $view->getTarget(), 'A click on a link to a fragment goes there.');
        } finally {
            $tui->stop();
        }
    }

    public function testAViewDoesNotMountThroughARendererThatHidesTheOneItDecorates()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5), renderer: new DecoratingRenderer(new Renderer(), false));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Select the TuiHtml renderer, or one that decorates it, before mounting an HTML view.');
        (new HtmlBuilder())->build('<p>Hello</p>')->mount($tui);
    }

    public function testSelectionReachesWidgetsAttachedBeforeIt()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $widget = new TextWidget('native');
        $tui->add($widget);
        $renderer = new Renderer();
        $tui->setRenderer($renderer);
        $this->assertSame($renderer, $widget->getContext()->getRenderer());
        $tui->start();
        try {
            $tui->processRender();
            $this->assertNotNull($tui->getWidgetRect($widget));
        } finally {
            $tui->stop();
        }
    }

    public function testSelectionCannotChangeAfterStartingOrStopping()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $tui->start();
        $tui->stop();
        $this->expectException(TuiLogicException::class);
        $tui->setRenderer(new Renderer());
    }

    public function testUnmountingAfterAnotherRendererIsSelected()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $renderer = new Renderer();
        $tui->setRenderer($renderer);
        $view = (new HtmlBuilder())->build('<style>p { color: red }</style><p id="text">x</p>');
        $view->mount($tui);
        $this->assertContains($view->getStyleSheet(), $renderer->getStyleSheet()->getProviders());
        $tui->setRenderer(new NativeRenderer());
        $view->unmount();
        $this->assertNotContains($view->getStyleSheet(), $renderer->getStyleSheet()->getProviders());
        $this->assertNull($view->getById('text')->getParent());
    }

    public function testAMountedViewTakesPointerAndNavigationAfterAnotherRendererIsSelected()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $tui->setRenderer(new Renderer());
        $view = (new HtmlBuilder())->build('<p id="text">x</p>');
        $view->mount($tui);
        $tui->setRenderer(new NativeRenderer());

        $view->click(0, 0);
        $view->navigate('text');
        $this->addToAssertionCount(1);
    }

    public function testAStyleSheetAddedBetweenFramesChangesTheStylesResolvedBefore()
    {
        $renderer = new Renderer();
        $text = new TextWidget('x');
        $text->addStyleClass('on');
        $this->assertNull($renderer->resolveStyle($text)->getBold());

        $renderer->addStyleSheet(new StyleSheet(['.on' => new Style(bold: true)]));

        $this->assertTrue($renderer->resolveStyle($text)->getBold());
    }

    public function testTheStylesOfAMountedViewFollowItToAnotherHtmlRenderer()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $tui->setRenderer($first = new Renderer());
        $view = (new HtmlBuilder())->build('<style>p { color: red }</style><p id="text">x</p>');
        $view->mount($tui);

        $tui->setRenderer($second = new Renderer());

        $this->assertNotContains($view->getStyleSheet(), $first->getStyleSheet()->getProviders());
        $this->assertContains($view->getStyleSheet(), $second->getStyleSheet()->getProviders());
        $view->unmount();
        $this->assertNotContains($view->getStyleSheet(), $second->getStyleSheet()->getProviders());
    }

    public function testNativeContainersAndCustomWidgetsKeepTheirContracts()
    {
        $widget = new class extends AbstractWidget {
            public ?RenderContext $received = null;

            public function render(RenderContext $context): array
            {
                $this->received = $context;

                return ['native'];
            }
        };
        $container = new ContainerWidget();
        $container->add($widget);
        $widget->setStyle(new Style(null, null, null, 'red'));
        $renderer = new Renderer();
        $lines = $renderer->renderFrame($container, 20, 5);
        $this->assertStringContainsString('native', implode('', $lines->toArray()));
        $this->assertInstanceOf(RenderContext::class, $widget->received);
        $this->assertInstanceOf(Style::class, $widget->received->getStyle());
        $this->assertNotNull($renderer->getWidgetRect($widget));
    }

    public function testNativeStylesheetOverridesAreCalled()
    {
        $sheet = new class extends StyleSheet {
            public function resolve(AbstractWidget $widget, ?int $columns = null): Style
            {
                return parent::resolve($widget, $columns)->withBold(40 === $columns);
            }
        };
        $renderer = new Renderer();
        $renderer->addStyleSheet($sheet);
        $root = new ContainerWidget();
        $widget = new TextWidget('native');
        $root->add($widget);
        $renderer->renderFrame($root, 40, 5);
        $this->assertTrue($renderer->resolveStyle($widget)->getBold());
    }

    public function testNativeThemesSurviveRendererSelection()
    {
        $widget = new TextWidget('native');
        $sheet = new StyleSheet([TextWidget::class => new Style(null, null, null, null, true)]);
        $tui = new Tui($sheet, new VirtualTerminal(20, 5));
        $tui->add($widget);
        $renderer = new Renderer();
        $tui->setRenderer($renderer);
        $tui->start();
        try {
            $tui->processRender();
            $this->assertTrue($renderer->resolveStyle($widget)->getBold());
        } finally {
            $tui->stop();
        }
    }

    public function testExtensionStateIsOwnedByTheWidget()
    {
        $widget = new ContainerWidget();
        $state = ContainerState::of($widget);
        $this->assertSame($state, ContainerState::of($widget));
        $other = new ContainerWidget();
        $this->assertNotSame($state, ContainerState::of($other));
        $reference = \WeakReference::create($widget);
        unset($state, $widget);
        gc_collect_cycles();
        $this->assertNull($reference->get());
    }

    public function testStoppingRestoresTheTerminalCursorColor()
    {
        $terminal = new VirtualTerminal(20, 5);
        $tui = new Tui(null, $terminal);
        $tui->setRenderer(new Renderer());
        $view = (new HtmlBuilder())->build('<input id="field" style="caret-color: #123456">');
        $view->mount($tui);
        $tui->setFocus($view->getById('field'));
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString("\x1b]12;#123456\x07", $terminal->consumeOutput());
        $tui->stop();
        $this->assertStringContainsString("\x1b]112\x07", $terminal->consumeOutput());
    }

    public function testMediaPreferencesRequestAnotherFrame()
    {
        $renderer = new Renderer();
        $renderer->setMediaContext((new MediaContext())->withColorScheme(ColorScheme::Light));
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $tui->setRenderer($renderer);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>@media (prefers-color-scheme: dark) { p { display: none } }</style><p id="text">visible</p>');
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $this->assertNotNull($tui->getWidgetRect($view->getById('text')));
            $renderer->setMediaContext($renderer->getMediaContext()->withColorScheme(ColorScheme::Dark));
            $tui->processRender();
            $this->assertNull($tui->getWidgetRect($view->getById('text')));
        } finally {
            $tui->stop();
        }
    }
}

final class DecoratingRenderer implements RendererDecoratorInterface, FocusPolicyInterface
{
    /**
     * @param array{int, int} $offset Where it draws the frame of the renderer it decorates, like a window showing it lower
     */
    public function __construct(private readonly RendererInterface&FocusPolicyInterface $inner, private readonly bool $exposed = true, private readonly array $offset = [0, 0])
    {
    }

    public function getDecoratedRenderer(): RendererInterface
    {
        return $this->exposed ? $this->inner : new NativeRenderer();
    }

    public function allowsFocus(AbstractWidget $widget): bool
    {
        return $this->inner->allowsFocus($widget);
    }

    public function orderFocusCycle(array $widgets): array
    {
        return $this->inner->orderFocusCycle($widgets);
    }

    public function addStyleSheet(StyleSheet $styleSheet): void
    {
        $this->inner->addStyleSheet($styleSheet);
    }

    public function resolveElement(AbstractWidget $widget, string $element): Style
    {
        return $this->inner->resolveElement($widget, $element);
    }

    public function invalidateStyles(): void
    {
        $this->inner->invalidateStyles();
    }

    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface
    {
        return $this->inner->createFrameWriter($terminal);
    }

    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        return $this->inner->getWidgetRect($widget)?->withOffset($this->offset[0], $this->offset[1]);
    }

    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        return $this->inner->renderFrame($root, $columns, $rows);
    }

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        return $this->inner->renderWidgetLines($widget, $context);
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        return $this->inner->resolveStyle($widget);
    }
}
