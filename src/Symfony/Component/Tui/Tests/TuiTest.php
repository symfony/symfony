<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class TuiTest extends TestCase
{
    public function testBasicRender()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $tui->add(new TextWidget('Hello World'));

        $tui->start();
        $tui->processRender();

        $output = $terminal->getOutput();
        $this->assertStringContainsString('Hello World', $output);
    }

    public function testMultipleComponents()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $tui->add(new TextWidget('First'));
        $tui->add(new TextWidget('Second'));

        $tui->start();
        $tui->processRender();

        $output = $terminal->getOutput();
        $this->assertStringContainsString('First', $output);
        $this->assertStringContainsString('Second', $output);
    }

    public function testContainerOperations()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        $tui->start();

        $text = new TextWidget('Hello');
        $tui->add($text);
        $tui->processRender();

        $this->assertStringContainsString('Hello', $terminal->getOutput());

        $terminal->clearOutput();
        $tui->remove($text);
        $tui->requestRender();
        $tui->processRender();

        $this->assertStringNotContainsString('Hello', $terminal->getOutput());

        $tui->stop();
    }

    public function testClear()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        $tui->start();

        $tui->add(new TextWidget('One'));
        $tui->add(new TextWidget('Two'));
        $tui->processRender();

        $output = $terminal->getOutput();
        $this->assertStringContainsString('One', $output);
        $this->assertStringContainsString('Two', $output);

        $terminal->clearOutput();
        $tui->clear();
        $tui->requestRender();
        $tui->processRender();

        $output = $terminal->getOutput();
        $this->assertStringNotContainsString('One', $output);
        $this->assertStringNotContainsString('Two', $output);

        $tui->stop();
    }

    public function testFocus()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $text = new TextWidget('Hello');
        $tui->add($text);

        $this->assertNull($tui->getFocus());

        $tui->setFocus($text);
        $this->assertSame($text, $tui->getFocus());

        $tui->setFocus(null);
        $this->assertNull($tui->getFocus());
    }

    public function testRootAutoFocusesFirstFocusable()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $tui->add(new TextWidget('Hello'));

        $input = new InputWidget();
        $tui->add($input);

        $this->assertSame($input, $tui->getFocus());
    }

    public function testRequestRenderForce()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $tui->add(new TextWidget('Hello'));

        $tui->start();
        $tui->processRender();

        // Force re-render should work
        $tui->requestRender(true);
        $tui->processRender();

        $this->assertStringContainsString('Hello', $terminal->getOutput());
    }

    public function testTickInvalidationQueuesRenderWithoutExplicitRequest()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        $text = new TextWidget('0');
        $ticks = 0;

        $tui->add($text);
        $tui->onTick(static function () use ($text, &$ticks): void {
            if (0 === $ticks) {
                $text->setText('1');
            }
            ++$ticks;
        });

        $tui->start();
        $tui->processRender();
        $terminal->clearOutput();

        $tui->tick();
        $this->assertSame('', $terminal->getOutput());

        $tui->tick();
        $this->assertStringContainsString('1', $terminal->getOutput());

        $tui->stop();
    }

    public function testOnTickReceivesDeltaTime()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        $deltas = [];

        $tui->onTick(static function (TickEvent $event) use (&$deltas): void {
            $deltas[] = $event->getDeltaTime();
        });

        $tui->start();
        $tui->tick();
        usleep(2_000);
        $tui->tick();
        $tui->stop();

        $this->assertCount(2, $deltas);
        $this->assertSame(0.0, $deltas[0]);
        $this->assertGreaterThan(0.0, $deltas[1]);
    }

    public function testRenderAllLinesRespectWidth()
    {
        $renderer = new Renderer(new StyleSheet([':root' => new Style(gap: 1)]));

        $root = new ContainerWidget();
        $root->add(new TextWidget('Short'));
        $root->add(new TextWidget('This is a longer text that should wrap properly.')->setStyle(Style::padding([0, 1])));
        $root->add(new TextWidget('End'));

        $lines = $renderer->render($root, 40, 24);

        foreach ($lines as $i => $line) {
            $width = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                40,
                $width,
                \sprintf('Line %d exceeds width: %d', $i, $width),
            );
        }
    }

    public function testSimulateInput()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $input = new InputWidget();
        $tui->add($input);
        $tui->setFocus($input);

        $tui->start();

        // Simulate typing
        $terminal->simulateInput('H');
        $terminal->simulateInput('i');

        $this->assertSame('Hi', $input->getValue());
    }

    public function testSimulateInputAfterStop()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $input = new InputWidget();
        $tui->add($input);
        $tui->setFocus($input);

        $tui->start();
        $terminal->simulateInput('A');
        $tui->stop();

        // After stop, simulateInput should be a no-op
        $terminal->simulateInput('B');

        $this->assertSame('A', $input->getValue());
    }

    public function testSimulateResize()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);

        $tui->add(new TextWidget('Hello'));
        $tui->start();

        $this->assertSame(80, $terminal->getColumns());
        $this->assertSame(24, $terminal->getRows());

        // Simulate resize
        $terminal->simulateResize(120, 40);

        $this->assertSame(120, $terminal->getColumns());
        $this->assertSame(40, $terminal->getRows());
    }

    public function testDefaultRendersContentAtTop()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $tui->add(new TextWidget('Header'));
        $tui->start();
        $tui->processRender();

        $output = $terminal->getOutput();
        $lines = explode("\r\n", $output);
        $stripped = array_map(static fn (string $l) => AnsiUtils::stripAnsiCodes($l), $lines);

        // Content should start at the first line, not be pushed to the bottom
        $headerLineIndex = null;
        foreach ($stripped as $i => $line) {
            if (str_contains($line, 'Header')) {
                $headerLineIndex = $i;
                break;
            }
        }

        $this->assertSame(0, $headerLineIndex, 'Content should be on the first line by default (top-aligned)');
    }

    public function testSimulateResizeTriggersRender()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $tui->add(new TextWidget('Hello'));
        $tui->start();
        $tui->processRender();

        // Clear output to detect new render
        $terminal->clearOutput();

        // Simulate resize - should trigger render request
        $terminal->simulateResize(60, 20);
        $tui->processRender();

        $this->assertStringContainsString('Hello', $terminal->getOutput());
    }

    public function testInputEventCanConsumeGlobalInputBeforeFocusedWidget()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $input = new InputWidget();
        $tui->add($input);
        $tui->setFocus($input);

        $called = false;
        $globalKeys = new Keybindings(['quit' => [Key::ctrl('c')]]);
        $tui->addListener(static function (InputEvent $event) use (&$called, $globalKeys): void {
            if ($globalKeys->matches($event->getData(), 'quit')) {
                $called = true;
                $event->stopPropagation();
            }
        });

        $tui->handleInput("\x03");

        $this->assertTrue($called);
        $this->assertSame('', $input->getValue());
    }

    public function testInputEventCanPassThroughToFocusedWidget()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $input = new InputWidget();
        $tui->add($input);
        $tui->setFocus($input);

        $globalKeys = new Keybindings(['quit' => [Key::ctrl('c')]]);
        $tui->addListener(static function (InputEvent $event) use ($globalKeys): void {
            if ($globalKeys->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
            }
        });

        $tui->handleInput('a');

        $this->assertSame('a', $input->getValue());
    }

    public function testInputEventCanConsumeKittyCtrlCSequence()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $input = new InputWidget();
        $tui->add($input);
        $tui->setFocus($input);

        $called = false;
        $globalKeys = new Keybindings(['quit' => [Key::ctrl('c')]]);
        $tui->addListener(static function (InputEvent $event) use (&$called, $globalKeys): void {
            if ($globalKeys->matches($event->getData(), 'quit')) {
                $called = true;
                $event->stopPropagation();
            }
        });

        $tui->handleInput("\x1b[99;5u");

        $this->assertTrue($called);
        $this->assertSame('', $input->getValue());
    }

    public function testEscapeKeyIsDispatchedViaSimulateInput()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $received = null;
        $tui->addListener(static function (InputEvent $event) use (&$received): void {
            $received = $event->getData();
        });

        $tui->start();
        $terminal->simulateInput("\x1b");

        $this->assertSame("\x1b", $received);
        $tui->stop();
    }

    public function testStopFromInputEventListenerDoesNotCrash()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $keys = new Keybindings(['quit' => [Key::UP]]);
        $tui->addListener(static function (InputEvent $event) use ($tui, $keys): void {
            if ($keys->matches($event->getData(), 'quit')) {
                $tui->stop();
            }
        });

        $tui->start();
        $this->assertTrue($tui->isRunning());

        // Arrow Up is a multi-byte sequence dispatched during process().
        // stop() sets stdinBuffer to null; the nullsafe flush() must not crash.
        $terminal->simulateInput("\x1b[A");

        $this->assertFalse($tui->isRunning());
    }

    public function testGetById()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $text = new TextWidget('Hello');
        $text->setId('greeting');
        $tui->add($text);

        $found = $tui->getById('greeting');
        $this->assertSame($text, $found);
    }

    public function testGetByIdThrowsWhenNotFound()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No widget found with id "missing"');

        $tui->getById('missing');
    }

    public function testGetByIdFindsNestedWidget()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $container = new ContainerWidget();
        $inner = new TextWidget('Deep');
        $inner->setId('deep');
        $container->add($inner);
        $tui->add($container);

        $found = $tui->getById('deep');
        $this->assertSame($inner, $found);
    }

    public function testOnInfersEventClassFromClosureTypeHint()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $received = null;
        $tui->addListener(static function (InputEvent $event) use (&$received): void {
            $received = $event->getData();
        });

        $tui->start();
        $terminal->simulateInput('x');

        $this->assertSame('x', $received);
        $tui->stop();
    }

    public function testOnInfersEventClassWithPriority()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $order = [];
        $tui->addListener(static function (InputEvent $event) use (&$order): void {
            $order[] = 'low';
        });
        $tui->addListener(static function (InputEvent $event) use (&$order): void {
            $order[] = 'high';
        }, 10);

        $tui->start();
        $terminal->simulateInput('a');

        $this->assertSame(['high', 'low'], $order);
        $tui->stop();
    }

    public function testOnThrowsWhenEventClassCannotBeInferred()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot infer the event class');

        $tui->addListener(static function ($event): void {});
    }

    public function testOnThrowsWhenTypeHintIsNotAClass()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot infer the event class');

        $tui->addListener(static function (int $event): void {});
    }

    public function testOnWorksWithInvokableObject()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);

        $received = null;
        $listener = new class($received) {
            public function __construct(private mixed &$received) {}

            public function __invoke(InputEvent $event): void
            {
                $this->received = $event->getData();
            }
        };

        $tui->addListener($listener);

        $tui->start();
        $terminal->simulateInput('z');

        $this->assertSame('z', $received);
        $tui->stop();
    }
}
