<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui;

use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Tui\Event\AbstractEvent;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Focus\FocusManager;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Loop\AdaptativeTicker;
use Symfony\Component\Tui\Loop\TickRuntimeInterface;
use Symfony\Component\Tui\Loop\TickScheduler;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Render\RenderRequestorInterface;
use Symfony\Component\Tui\Render\ScreenWriter;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\Terminal;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\WidgetTree;

/**
 * Main TUI class for managing terminal UI.
 *
 * This class orchestrates:
 * - Terminal lifecycle (start/stop)
 * - Event loop integration
 * - Focus management
 * - Input handling
 *
 * The root container is created internally.
 * Use add(), remove(), and clear() to build the widget tree.
 * Style the root via the stylesheet using the ":root" pseudo-class selector.
 *
 * Rendering is delegated to:
 * - Renderer: widget tree → lines (content generation)
 * - ScreenWriter: lines → terminal (differential output)
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Tui implements RenderRequestorInterface, TickRuntimeInterface
{
    private ContainerWidget $root;
    private Keybindings $keybindings;
    private Renderer $renderer;
    private ScreenWriter $screenWriter;
    private WidgetTree $widgetTree;
    private FocusManager $focusManager;
    private TickScheduler $tickScheduler;
    private AdaptativeTicker $adaptativeTicker;
    private EventDispatcherInterface $eventDispatcher;

    /** @var (\Closure(TickEvent): mixed)|null */
    private ?\Closure $onTick = null;

    private bool $renderRequested = false;
    private bool $running = false;
    private bool $stopped = false;
    private bool $ticking = false;
    private ?float $lastTickAt = null;
    private ?bool $lastTickBusyHint = null;

    /** @var Suspension<mixed>|null */
    private ?Suspension $runSuspension = null;

    /**
     * @param Renderer|null $renderer @internal Override the default renderer; framework-internal use only
     */
    public function __construct(
        ?StyleSheet $styleSheet = null,
        private readonly TerminalInterface $terminal = new Terminal(),
        ?Keybindings $keybindings = null,
        ?FontRegistry $fontRegistry = null,
        ?Renderer $renderer = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->keybindings = $keybindings ?? new Keybindings();
        $this->root = new ContainerWidget();
        $this->root->expandVertically(true);
        $this->renderer = $renderer ?? new Renderer($styleSheet, $fontRegistry);
        $this->screenWriter = new ScreenWriter($terminal);
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();

        // Share the KeyParser so Kitty protocol state is consistent
        $this->focusManager = new FocusManager(
            $this,
            keybindings: $this->keybindings,
            eventDispatcher: $this->eventDispatcher,
        );

        $this->widgetTree = new WidgetTree($this, $this->keybindings, $this->focusManager, $this->renderer, $this->terminal, $this->eventDispatcher);
        $this->widgetTree->setRoot($this->root);
        $this->tickScheduler = new TickScheduler();
        $this->adaptativeTicker = new AdaptativeTicker($this);
    }

    /**
     * Add a child widget to the root container.
     *
     * @return $this
     */
    public function add(AbstractWidget $widget): static
    {
        $this->root->add($widget);

        return $this;
    }

    /**
     * Remove a child widget from the root container.
     *
     * @return $this
     */
    public function remove(AbstractWidget $widget): static
    {
        $this->root->remove($widget);

        return $this;
    }

    /**
     * Remove all child widgets from the root container.
     *
     * @return $this
     */
    public function clear(): static
    {
        $this->root->clear();

        return $this;
    }

    /**
     * Find a widget by ID in the widget tree.
     */
    public function getById(string $id): AbstractWidget
    {
        $widget = $this->root->findById($id);

        if (null === $widget) {
            throw new InvalidArgumentException(\sprintf('No widget found with id "%s".', $id));
        }

        return $widget;
    }

    /**
     * Add a stylesheet on top of the existing ones.
     *
     * User stylesheets are merged on top of defaults (last wins for same selectors).
     *
     * @return $this
     */
    public function addStyleSheet(StyleSheet $styleSheet): static
    {
        $this->renderer->addStyleSheet($styleSheet);

        return $this;
    }

    /**
     * Run the main event loop using Revolt.
     *
     * This method runs the TUI using Revolt's event loop, allowing
     * async operations (like HTTP streaming) to run concurrently.
     *
     * This blocks until stop() is called.
     */
    public function run(): void
    {
        try {
            $this->start();
            $this->runSuspension = EventLoop::getSuspension();
            $this->refreshLoopDriver();

            // Block until stop() is called
            $this->runSuspension->suspend();
        } finally {
            $this->runSuspension = null;
            // Ensure terminal is restored
            $this->stop();
        }
    }

    /**
     * Start the TUI.
     */
    public function start(): void
    {
        $this->running = true;
        $this->stopped = false;
        $this->lastTickAt = null;
        $this->lastTickBusyHint = null;
        $this->terminal->start($this->handleInput(...), $this->requestRender(...), function (): void {
            $this->keybindings->setKittyProtocolActive(true);
        });
        $this->terminal->hideCursor();
        $this->requestRender();
    }

    /**
     * Run one iteration of the TUI loop.
     * Processes scheduled tasks, rendering, and tick callback.
     */
    public function tick(): void
    {
        // Guard against re-entrant ticks. When the onTick callback suspends
        // a fiber (e.g., async file I/O via Amp), the Revolt event loop may
        // fire another repeat-timer callback while the previous tick is still
        // suspended. Without this guard, two fibers would concurrently mutate
        // the agent state machine and render to the terminal, corrupting the
        // display.
        if ($this->ticking) {
            return;
        }

        $this->ticking = true;
        $now = microtime(true);
        $deltaTime = null === $this->lastTickAt ? 0.0 : max(0.0, $now - $this->lastTickAt);
        $this->lastTickAt = $now;
        $revisionBeforeTick = $this->root->getRenderRevision();

        try {
            $this->tickScheduler->runDue();
            $this->processRender();
            $this->lastTickBusyHint = $this->invokeTickCallback($deltaTime);

            if ($this->root->getRenderRevision() !== $revisionBeforeTick) {
                $this->requestRender();
            }
        } finally {
            $this->ticking = false;
            $this->refreshLoopDriver();
        }
    }

    /**
     * Stop the TUI and restore terminal state.
     */
    public function stop(): void
    {
        $this->running = false;
        $this->adaptativeTicker->stop();
        $this->tickScheduler->clear();
        $this->lastTickAt = null;
        $this->lastTickBusyHint = null;
        $this->resumeRunSuspension();

        if ($this->stopped) {
            return;
        }
        $this->stopped = true;

        // Move cursor to end of content
        $state = $this->screenWriter->getState();
        if ($state['line_count'] > 0) {
            $lineDiff = $state['line_count'] - $state['cursor_row'];

            if ($lineDiff > 0) {
                $this->terminal->write("\x1b[{$lineDiff}B");
            } elseif ($lineDiff < 0) {
                $this->terminal->write("\x1b[".(-$lineDiff).'A');
            }

            $this->terminal->write("\r\n");
        }

        // Restore default cursor shape (DECSCUSR 0) and show cursor
        $this->terminal->write("\x1b[0 q");
        $this->terminal->showCursor();
        $this->terminal->stop();
    }

    /**
     * Check if the TUI is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * @param callable(TickEvent): mixed $onTick
     *
     * Return true while active work is in progress (fast 100Hz ticking),
     * false when idle (no polling), or null/void to use fallback idle polling
     *
     * @return $this
     */
    public function onTick(?callable $onTick): static
    {
        $this->onTick = $onTick ? $onTick(...) : null;
        $this->lastTickAt = null;
        $this->lastTickBusyHint = null;
        $this->refreshLoopDriver();

        return $this;
    }

    /**
     * Register a listener for a widget event.
     *
     * The event class is inferred from the listener's first parameter type hint:
     *
     *     $tui->addListener(function (InputEvent $event) { ... });
     *
     * This is the primary way to react to widget events (submit, cancel,
     * change, select, etc.). All events dispatched by any widget in the
     * tree are routed through this single dispatcher.
     *
     * Use {@see AbstractEvent::getTarget()} to filter by source widget when
     * listening for a shared event type like CancelEvent.
     *
     * @param callable $listener The listener to invoke; its first parameter's type hint determines the event class
     * @param int      $priority Higher = called earlier (default 0)
     *
     * @return $this
     */
    public function addListener(callable $listener, int $priority = 0): static
    {
        $this->eventDispatcher->addListener($this->resolveEventClass($listener), $listener, $priority);

        return $this;
    }

    /**
     * @return class-string
     */
    private function resolveEventClass(callable $listener): string
    {
        $params = (new \ReflectionFunction($listener(...)))->getParameters();
        if (!$params || !($type = $params[0]->getType()) instanceof \ReflectionNamedType || $type->isBuiltin()) {
            throw new InvalidArgumentException('Cannot infer the event class: the listener\'s first parameter must have an event class type hint.');
        }

        if (!class_exists($class = $type->getName())) {
            throw new InvalidArgumentException(\sprintf('Cannot use "%s" as an event class as the class does not exist.', $class));
        }

        return $class;
    }

    /**
     * Get the event dispatcher.
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * Get the terminal.
     */
    public function getTerminal(): TerminalInterface
    {
        return $this->terminal;
    }

    /**
     * Set the focused component.
     *
     * @return $this
     */
    public function setFocus(?AbstractWidget $component): static
    {
        $this->focusManager->setFocus($component);

        return $this;
    }

    /**
     * Get the currently focused component.
     */
    public function getFocus(): ?AbstractWidget
    {
        return $this->focusManager->getFocus();
    }

    /**
     * Get the focus manager.
     */
    public function getFocusManager(): FocusManager
    {
        return $this->focusManager;
    }

    /**
     * Request a render on the next tick.
     *
     * @param bool $force If true, clear all cached state and do full re-render
     */
    public function requestRender(bool $force = false): void
    {
        if ($force) {
            $this->screenWriter->reset();
        }

        $this->renderRequested = true;
        $this->refreshLoopDriver();
    }

    /**
     * Set the scroll offset (lines from bottom).
     *
     * When the content exceeds the viewport, the viewport normally shows
     * the bottom portion. A positive scroll offset shifts the viewport
     * up by that many lines.
     */
    public function setScrollOffset(int $offset): void
    {
        $this->screenWriter->setScrollOffset($offset);
    }

    /**
     * Schedule a repeating callback in the internal TUI scheduler.
     *
     * @internal
     *
     * @param callable(): void $callback
     */
    public function scheduleInterval(callable $callback, float $intervalSeconds): string
    {
        $id = $this->tickScheduler->schedule($callback, $intervalSeconds);

        $this->refreshLoopDriver();

        return $id;
    }

    /**
     * Cancel a callback previously registered with scheduleInterval().
     *
     * @internal
     */
    public function cancelInterval(string $id): void
    {
        $this->tickScheduler->cancel($id);
        $this->refreshLoopDriver();
    }

    /**
     * Process any pending renders.
     *
     * Called automatically by tick(). Only call this directly
     * if you are driving the loop manually instead of using run().
     */
    public function processRender(): void
    {
        if ($this->renderRequested) {
            $this->renderRequested = false;
            $columns = $this->terminal->getColumns();
            $rows = $this->terminal->getRows();
            $this->screenWriter->writeLines($this->renderer->render($this->root, $columns, $rows));
        }
    }

    /**
     * Handle input from the terminal.
     */
    public function handleInput(string $data): void
    {
        $event = $this->eventDispatcher->dispatch(new InputEvent($data));
        if ($event->isPropagationStopped()) {
            return;
        }

        if ($this->focusManager->handleInput($data)) {
            return;
        }

        // Pass input to focused component
        $focused = $this->focusManager->getFocus();
        if ($focused instanceof FocusableInterface) {
            $revisionBeforeInput = $this->root->getRenderRevision();
            $focused->handleInput($data);
            if ($this->root->getRenderRevision() !== $revisionBeforeInput) {
                $this->requestRender();
            }
        }
    }

    private function invokeTickCallback(float $deltaTime): ?bool
    {
        $event = new TickEvent($deltaTime);
        $this->eventDispatcher->dispatch($event);

        if (null === $this->onTick) {
            return $event->hasBusyHint() ? $event->isBusy() : null;
        }

        $result = ($this->onTick)($event);

        if (\is_bool($result)) {
            return $result;
        }

        if ($event->hasBusyHint()) {
            return $event->isBusy();
        }

        return null;
    }

    private function refreshLoopDriver(): void
    {
        $this->adaptativeTicker->refresh($this->running, $this->renderRequested, $this->tickScheduler->getNextDelay(), null !== $this->onTick, $this->lastTickBusyHint);
    }

    private function resumeRunSuspension(): void
    {
        if (null === $this->runSuspension) {
            return;
        }

        $suspension = $this->runSuspension;
        $this->runSuspension = null;
        $suspension->resume(null);
    }
}
