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
use Symfony\Component\Tui\Event\RendererChangeEvent;
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\FrameWriterInterface;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * A rendering backend written against the public API only, like one shipped in another package.
 */
final class ThirdPartyRendererTest extends TestCase
{
    public function testTheContractsUseNoInternalType()
    {
        $internal = [];
        foreach ([RendererInterface::class, FrameWriterInterface::class, FocusPolicyInterface::class, LineBufferInterface::class] as $interface) {
            foreach ((new \ReflectionClass($interface))->getMethods() as $method) {
                $types = [$method->getReturnType(), ...array_map(static fn (\ReflectionParameter $p) => $p->getType(), $method->getParameters())];
                foreach ($types as $type) {
                    foreach ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType ? $type->getTypes() : [$type] as $named) {
                        if ($named instanceof \ReflectionNamedType && !$named->isBuiltin() && str_contains((string) (new \ReflectionClass($named->getName()))->getDocComment(), '@internal')) {
                            $internal[] = $interface.'::'.$method->getName().'(): '.$named->getName();
                        }
                    }
                }
            }
        }
        $this->assertSame([], $internal);
    }

    public function testAnotherRendererDrawsTheWidgetTree()
    {
        $terminal = new VirtualTerminal(20, 4);
        $renderer = new PlainRenderer();
        $tui = new Tui(null, $terminal, renderer: $renderer);
        $tui->add(new TextWidget('Hello'));
        $hidden = new InputWidget();
        $shown = new InputWidget();
        $renderer->refused = $hidden;
        $tui->add($hidden)->add($shown);
        $tui->start();
        $tui->processRender();

        $this->assertStringContainsString('[Hello]', $terminal->getOutput());
        $this->assertSame($shown, $tui->getFocusManager()->getFocus(), 'The focus policy of the renderer is asked.');
        $this->assertEquals(new WidgetRect(0, 0, 20, 1), $tui->getWidgetRect($renderer->drawn[0]));
        $tui->stop();
        $this->assertTrue($renderer->writer->closed);
    }

    public function testARendererThatFailsLeavesThePreviousOneInPlace()
    {
        $tui = new Tui(new StyleSheet(), new VirtualTerminal(20, 4), renderer: $previous = new PlainRenderer());
        $tui->add($text = new TextWidget('Hello'));
        $failing = new PlainRenderer();
        $failing->failsToWrite = true;
        $events = 0;
        $tui->addListener(static function (RendererChangeEvent $event) use (&$events): void {
            ++$events;
        });

        try {
            $tui->setRenderer($failing);
            $this->fail('The renderer fails.');
        } catch (\RuntimeException) {
        }

        $this->assertSame($previous, $tui->getRenderer());
        $this->assertFalse($previous->writer->closed, 'The previous frame writer still writes.');
        $this->assertSame(0, $failing->styleSheets);
        $this->assertSame(0, $events);
        $tui->start();
        $tui->processRender();
        $this->assertSame([$text], $previous->drawn);
        $tui->stop();
    }

    public function testARendererThatFailsToTakeTheStyleSheetsClosesItsWriter()
    {
        $tui = new Tui(new StyleSheet(), new VirtualTerminal(20, 4), renderer: $previous = new PlainRenderer());
        $failing = new PlainRenderer();
        $failing->failsToStyle = true;

        try {
            $tui->setRenderer($failing);
            $this->fail('The renderer fails.');
        } catch (\RuntimeException) {
        }

        $this->assertSame($previous, $tui->getRenderer());
        $this->assertTrue($failing->writer->closed);
        $this->assertFalse($previous->writer->closed);
    }

    public function testAPreviousWriterThatFailsToCloseLeavesTheNewRendererReady()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4), renderer: $previous = new PlainRenderer());
        $tui->add($a = new InputWidget())->add(new InputWidget());
        $tui->setFocus($a);
        $previous->writer->failsToClose = true;
        $events = 0;
        $tui->addListener(static function (RendererChangeEvent $event) use (&$events): void {
            ++$events;
        });
        $renderer = new PlainRenderer();
        $renderer->refused = $a;

        try {
            $tui->setRenderer($renderer);
            $this->fail('The previous writer fails.');
        } catch (\RuntimeException) {
        }

        $this->assertSame($renderer, $tui->getRenderer());
        $this->assertSame(1, $events, 'The listeners are told.');
        $this->assertNull($tui->getFocusManager()->getFocus(), 'The focus is checked.');
    }

    public function testSelectingTheSelectedRendererAgainDoesNothing()
    {
        $tui = new Tui(new StyleSheet(), new VirtualTerminal(20, 4), renderer: $renderer = new PlainRenderer());
        $tui->addStyleSheet(new StyleSheet());
        $writer = $renderer->writer;

        $tui->setRenderer($renderer);

        $this->assertSame(2, $renderer->styleSheets, 'Its style sheets are not added again.');
        $this->assertSame($writer, $renderer->writer);
        $this->assertFalse($writer->closed);
    }

    public function testSelectingARendererClosesThePreviousWriterOnce()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4), renderer: $previous = new PlainRenderer());
        $tui->setRenderer($renderer = new PlainRenderer());

        $this->assertTrue($previous->writer->closed);
        $this->assertFalse($renderer->writer->closed);
        $tui->start();
        $tui->stop();
        $this->assertTrue($renderer->writer->closed);
    }

    public function testStoppingGivesTheTerminalBackWhenTheWriterFails()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal, renderer: $renderer = new PlainRenderer());
        $tui->start();
        $renderer->writer->failsToClose = true;
        $terminal->clearOutput();

        try {
            $tui->stop();
            $this->fail('The writer fails.');
        } catch (\RuntimeException) {
        }

        $this->assertStringContainsString("\x1b[?25h", $terminal->getOutput(), 'The cursor is shown again.');
        $this->assertFalse($tui->isRunning());
    }

    public function testARendererThatRefusesTheFocusedWidgetTakesTheFocusAway()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($a = new InputWidget())->add($b = new InputWidget());
        $tui->setFocus($a);
        $renderer = new PlainRenderer();
        $renderer->refused = $a;

        $tui->setRenderer($renderer);

        $this->assertNull($tui->getFocusManager()->getFocus());
        $this->assertFalse($a->isFocused());
        $tui->handleInput("\x1b[17~"); // F6
        $this->assertSame($b, $tui->getFocusManager()->getFocus());
    }

    public function testAFocusedWidgetTheRendererNoLongerAllowsGetsNoInput()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4), renderer: $renderer = new PlainRenderer());
        $tui->add($input = new InputWidget())->add(new InputWidget());
        $tui->setFocus($input);
        $tui->handleInput('a');
        $this->assertSame('a', $input->getValue());

        // Like a widget the renderer hides, which it knows once it draws a frame
        $renderer->refused = $input;
        $tui->requestRender();
        $tui->processRender();
        $tui->handleInput('b');

        $this->assertSame('a', $input->getValue());
        $this->assertNull($tui->getFocusManager()->getFocus());
    }

    public function testADisabledFocusedWidgetLosesTheFocus()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($input = new InputWidget())->add($next = new InputWidget());
        $tui->setFocus($input);

        $input->setState(AbstractWidget::STATE_DISABLED);
        $tui->handleInput('x');

        $this->assertNull($tui->getFocusManager()->getFocus());
        $this->assertSame('', $input->getValue());
        $tui->handleInput("\x1b[17~"); // F6
        $this->assertSame($next, $tui->getFocusManager()->getFocus());
    }

    public function testExtensionStateLivesAsLongAsTheWidget()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget = new TextWidget('x'));
        $state = $widget->getExtensionState(\ArrayObject::class);
        $state['scroll'] = 3;

        $tui->remove($widget);
        $tui->add($widget);
        $tui->setRenderer(new PlainRenderer());

        $this->assertSame($state, $widget->getExtensionState(\ArrayObject::class));
        $this->assertNotSame($state, (new TextWidget('x'))->getExtensionState(\ArrayObject::class));
    }

    public function testTheNativeRendererSelectedAgainDrawsTheSameFrame()
    {
        $draw = static function (bool $switch): string {
            $terminal = new VirtualTerminal(30, 6);
            $tui = new Tui(new StyleSheet()->addRule('.title', new Style()->withColor('green')->withBold(true)), $terminal);
            $tui->add(new TextWidget('Name')->addStyleClass('title'));
            $tui->add($input = new InputWidget());
            $input->setValue('Ada');
            if ($switch) {
                $tui->setRenderer(new PlainRenderer());
                $tui->setRenderer(new Renderer());
            }
            $tui->getFocusManager()->setFocus($input);
            $tui->start();
            $tui->processRender();
            $tui->stop();

            return $terminal->getOutput();
        };

        $this->assertSame($draw(false), $draw(true));
    }

    public function testAWidgetAddedBackIsDrawnAfresh()
    {
        $terminal = new VirtualTerminal(20, 4);
        $screen = new ScreenBuffer(20, 4);
        $tui = new Tui(null, $terminal);
        $tui->add($text = new TextWidget('Hello'));
        $tui->start();
        $tui->processRender();
        $screen->write($terminal->consumeOutput());
        $this->assertStringContainsString('Hello', $screen->getScreen());

        // Outside a tick, a change asks for the frame itself
        $tui->remove($text);
        $tui->requestRender();
        $tui->processRender();
        $screen->write($terminal->consumeOutput());
        $this->assertStringNotContainsString('Hello', $screen->getScreen());

        $text->setText('Bye');
        $tui->add($text);
        $tui->requestRender();
        $tui->processRender();
        $screen->write($terminal->consumeOutput());
        $tui->stop();

        $this->assertStringContainsString('Bye', $screen->getScreen());
    }

    public function testListenersCanBeRemoved()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $events = 0;
        $listener = static function (RendererChangeEvent $event) use (&$events): void {
            ++$events;
        };
        $tui->addListener($listener);
        $tui->setRenderer(new PlainRenderer());
        $tui->removeListener($listener);
        $tui->setRenderer(new PlainRenderer());

        $this->assertSame(1, $events);
    }
}

final class PlainRenderer implements RendererInterface, FocusPolicyInterface
{
    public ?AbstractWidget $refused = null;
    /** @var list<AbstractWidget> */
    public array $drawn = [];
    public PlainFrameWriter $writer;
    public int $styleSheets = 0;
    public bool $failsToWrite = false;
    public bool $failsToStyle = false;

    public function addStyleSheet(StyleSheet $styleSheet): void
    {
        if ($this->failsToStyle) {
            throw new \RuntimeException('Not a stylesheet for this renderer.');
        }
        ++$this->styleSheets;
    }

    public function resolveElement(AbstractWidget $widget, string $element): Style
    {
        return new Style();
    }

    public function invalidateStyles(): void
    {
    }

    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface
    {
        if ($this->failsToWrite) {
            throw new \RuntimeException('No terminal for this renderer.');
        }

        return $this->writer = new PlainFrameWriter($terminal);
    }

    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        $row = array_search($widget, $this->drawn, true);

        return false === $row ? null : new WidgetRect($row, 0, 20, 1);
    }

    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        $this->drawn = [];
        $lines = [];
        foreach ($root->all() as $widget) {
            if ($widget instanceof TextWidget) {
                $this->drawn[] = $widget;
                $lines[] = '['.$widget->getText().']';
            }
        }

        return new PlainLines($lines);
    }

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        return new PlainLines($widget->render($context));
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        return new Style();
    }

    public function allowsFocus(AbstractWidget $widget): bool
    {
        return $widget !== $this->refused;
    }

    public function orderFocusCycle(array $widgets): array
    {
        return $widgets;
    }
}

final class PlainFrameWriter implements FrameWriterInterface
{
    public bool $closed = false;
    public bool $failsToClose = false;
    private int $lineCount = 0;

    public function __construct(private readonly TerminalInterface $terminal)
    {
    }

    public function writeFrame(LineBufferInterface $lines): void
    {
        $this->terminal->write(implode("\r\n", $lines->toArray()));
        $this->lineCount = \count($lines);
    }

    public function reset(): void
    {
    }

    public function close(): void
    {
        if ($this->failsToClose) {
            throw new \RuntimeException('The terminal went away.');
        }
        $this->closed = true;
    }

    public function setScrollOffset(int $offset): void
    {
    }

    public function getState(): array
    {
        return ['cursor_row' => max(0, $this->lineCount - 1), 'line_count' => $this->lineCount];
    }

    public function onTerminalHeightChange(int $previousRows, int $rows): void
    {
        $this->reset();
    }
}

/**
 * @implements \IteratorAggregate<int, string>
 */
final class PlainLines implements LineBufferInterface, \IteratorAggregate
{
    /**
     * @param list<string> $lines
     */
    public function __construct(private readonly array $lines)
    {
    }

    public function getLine(int $index): string
    {
        return $this->lines[$index];
    }

    public function slice(int $offset, int $length): array
    {
        return \array_slice($this->lines, $offset, $length);
    }

    public function toArray(): array
    {
        return $this->lines;
    }

    public function getMaxVisibleWidth(): int
    {
        return max(0, ...array_map('strlen', $this->lines));
    }

    public function count(): int
    {
        return \count($this->lines);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->lines);
    }
}
