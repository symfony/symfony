<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * The styles the renderer resolves follow the widgets and the stylesheet.
 */
class StyleCacheTest extends TestCase
{
    public function testStyleFollowsAStateChangeBetweenFrames()
    {
        $renderer = new Renderer(new StyleSheet(['.btn:hover' => new Style(bold: true)]));
        [$root, $text] = $this->tree($renderer);
        $text->addStyleClass('btn');

        $this->assertNull($renderer->resolveStyle($text)->getBold());
        $text->setState('hover');
        $this->assertTrue($renderer->resolveStyle($text)->getBold());
        $this->assertStringContainsString("\x1b[1m", $renderer->renderFrame($root, 20, 3)->toArray()[0]);
        $text->setState('hover', false);
        $this->assertStringNotContainsString("\x1b[1m", $renderer->renderFrame($root, 20, 3)->toArray()[0]);
    }

    public function testStyleFollowsAClassChangeOfASibling()
    {
        $renderer = new Renderer(new StyleSheet(['.on' => new Style(bold: true)]));
        [$root, $text] = $this->tree($renderer);
        $root->add($sibling = new TextWidget('b'));

        $renderer->renderFrame($root, 20, 3);
        $sibling->addStyleClass('on');
        $this->assertNull($renderer->resolveStyle($text)->getBold());
        $this->assertTrue($renderer->resolveStyle($sibling)->getBold());
    }

    public function testChildDrawsTheBackgroundOfItsParentAfterAClassChangeOfTheParent()
    {
        $renderer = new Renderer(new StyleSheet(['.red' => new Style(background: 'red'), '.green' => new Style(background: 'green')]));
        $root = new ContainerWidget();
        $root->addStyleClass('red');
        $root->add($child = new ContainerWidget());
        $child->setStyle(new Style(border: Border::all(1, 'none')));
        $child->add(new TextWidget('a'));

        $this->assertStringContainsString("\x1b[41m", $renderer->renderFrame($root, 20, 5)->toArray()[0]);
        $root->setStyleClasses(['green']);
        $this->assertStringContainsString("\x1b[42m", $renderer->renderFrame($root, 20, 5)->toArray()[0]);
    }

    public function testStyleFollowsTheRootStateWhenAWidgetIsAdded()
    {
        $renderer = new Renderer(new StyleSheet([':root' => new Style(bold: true)]));
        $text = new TextWidget('a');

        $this->assertTrue($renderer->resolveStyle($text)->getBold());
        new ContainerWidget()->add($text);
        $this->assertNull($renderer->resolveStyle($text)->getBold());
    }

    public function testStyleFollowsTheFocus()
    {
        $renderer = new Renderer(new StyleSheet([InputWidget::class.':focus' => new Style(bold: true)]));
        $root = new ContainerWidget();
        $root->add($input = new InputWidget());

        $this->assertNull($renderer->resolveStyle($input)->getBold());
        $input->setFocused(true);
        $this->assertTrue($renderer->resolveStyle($input)->getBold());
    }

    public function testStyleFollowsAStyleSheetAddedBetweenResolutions()
    {
        $renderer = new Renderer();
        [, $text] = $this->tree($renderer);

        $this->assertNull($renderer->resolveStyle($text)->getBold());
        $renderer->addStyleSheet(new StyleSheet([TextWidget::class => new Style(bold: true)]));
        $this->assertTrue($renderer->resolveStyle($text)->getBold());
    }

    public function testStyleChangedFromBeforeRenderIsDrawnInTheSameFrame()
    {
        $renderer = new Renderer(new StyleSheet([':busy' => new Style(bold: true)]));
        $root = new ContainerWidget();
        $root->add(new class('a') extends TextWidget {
            public function beforeRender(): void
            {
                $this->setState('busy');
            }
        });

        $this->assertStringContainsString("\x1b[1m", $renderer->renderFrame($root, 20, 3)->toArray()[0]);
    }

    public function testAChangeDuringAFrameDoesNotResolveTheStylesOfTheOtherWidgetsAgain()
    {
        $styleSheet = new class extends StyleSheet {
            /** @var array<string, int> */
            public array $resolved = [];

            public function resolve(AbstractWidget $widget, ?int $columns = null): Style
            {
                if ($widget instanceof TextWidget) {
                    $this->resolved[$widget->getText()] = ($this->resolved[$widget->getText()] ?? 0) + 1;
                }

                return parent::resolve($widget, $columns);
            }
        };
        $tui = new Tui($styleSheet, new VirtualTerminal(20, 3));
        $renderer = $tui->getRenderer();
        $this->assertInstanceOf(Renderer::class, $renderer);
        $styleSheet = $renderer->getStyleSheet();
        $tui->add($a = new TextWidget('a'));
        $tui->add($spinner = new class('b') extends TextWidget {
            public int $frame = 0;

            public function beforeRender(): void
            {
                if ($this->frame) {
                    $this->setStyleClasses(['frame-'.$this->frame]);
                }
            }
        });
        $tui->add($c = new TextWidget('c'));
        $tui->start();
        $tui->processRender();
        $calm = $styleSheet->resolved;

        // Everything draws again, and "b" changes while the frame renders, like a spinner advancing
        $styleSheet->resolved = [];
        $a->setState('again');
        $c->setState('again');
        $spinner->frame = 1;
        $tui->requestRender();
        $tui->processRender();

        $this->assertSame($calm['a'], $styleSheet->resolved['a']);
        $this->assertSame($calm['c'], $styleSheet->resolved['c']);
    }

    public function testAStyleSetIsTheStyleKeptEvenWhenEqualToTheOneBefore()
    {
        $text = new TextWidget('a');
        $text->setStyle(new Style(bold: true));
        $revision = $text->getRenderRevision();

        $text->setStyle($second = new Style(bold: true));

        $this->assertSame($second, $text->getStyle());
        $this->assertGreaterThan($revision, $text->getRenderRevision());

        $revision = $text->getRenderRevision();
        $text->setStyle($second);
        $this->assertSame($revision, $text->getRenderRevision());
    }

    /**
     * @return array{ContainerWidget, TextWidget}
     */
    private function tree(Renderer $renderer): array
    {
        $root = new ContainerWidget();
        $root->add($text = new TextWidget('a'));

        return [$root, $text];
    }
}
