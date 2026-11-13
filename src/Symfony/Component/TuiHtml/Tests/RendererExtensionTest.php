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
use Symfony\Component\Tui\Exception\LogicException;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\Renderer;

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
        $this->expectException(LogicException::class);
        $tui->setRenderer(new Renderer());
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
        $state = \Symfony\Component\TuiHtml\Internal\ContainerState::of($widget);
        $this->assertSame($state, \Symfony\Component\TuiHtml\Internal\ContainerState::of($widget));
        $other = new ContainerWidget();
        $this->assertNotSame($state, \Symfony\Component\TuiHtml\Internal\ContainerState::of($other));
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
        $renderer->setMediaContext((new \Symfony\Component\TuiHtml\Style\MediaContext())->withColorScheme(\Symfony\Component\TuiHtml\Style\ColorScheme::Light));
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $tui->setRenderer($renderer);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>@media (prefers-color-scheme: dark) { p { display: none } }</style><p id="text">visible</p>');
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $this->assertNotNull($tui->getWidgetRect($view->getById('text')));
            $renderer->setMediaContext($renderer->getMediaContext()->withColorScheme(\Symfony\Component\TuiHtml\Style\ColorScheme::Dark));
            $tui->processRender();
            $this->assertNull($tui->getWidgetRect($view->getById('text')));
        } finally {
            $tui->stop();
        }
    }
}
