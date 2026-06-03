<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Tui\Event\AbstractEvent;
use Symfony\Component\Tui\Focus\FocusManager;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;

/**
 * Runtime context provided to widgets when attached to the tree.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class WidgetContext
{
    /** @var array<string, string> */
    private array $tickIds = [];

    /**
     * @internal constructed by the framework; user code receives the context via {@see AbstractWidget::onAttach()}
     */
    public function __construct(
        private readonly Tui $tui,
        private readonly Keybindings $keybindings,
        private readonly TerminalInterface $terminal,
        private readonly FocusManager $focusManager,
        private readonly Renderer $renderer,
        private readonly WidgetTree $widgetTree,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function keybindings(): Keybindings
    {
        return $this->keybindings;
    }

    public function stop(): void
    {
        $this->tui->stop();
    }

    public function requestRender(bool $force = false): void
    {
        $this->tui->requestRender($force);
    }

    public function dispatch(AbstractEvent $event): void
    {
        $this->eventDispatcher->dispatch($event);
        $this->tui->requestRender();
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function resolveElement(AbstractWidget $widget, string $element): Style
    {
        return $this->renderer->getStyleSheet()->resolveElement($widget, $element);
    }

    public function getTerminalColumns(): int
    {
        return $this->terminal->getColumns();
    }

    public function getTerminalRows(): int
    {
        return $this->terminal->getRows();
    }

    /**
     * @internal
     */
    public function getFocusManager(): FocusManager
    {
        return $this->focusManager;
    }

    /**
     * @return string[]
     */
    public function renderWidget(AbstractWidget $widget, RenderContext $context): array
    {
        return $this->renderer->renderWidget($widget, $context);
    }

    public function scheduleTick(callable $callback, float $intervalSeconds): string
    {
        $id = $this->tui->scheduleInterval($callback, $intervalSeconds);
        $this->tickIds[$id] = $id;

        return $id;
    }

    public function cancelTick(string $id): void
    {
        if (!isset($this->tickIds[$id])) {
            return;
        }

        $this->tui->cancelInterval($id);
        unset($this->tickIds[$id]);
    }

    /**
     * @internal
     */
    public function attachChild(AbstractWidget $parent, AbstractWidget $child): void
    {
        $this->widgetTree->attach($child, $parent);
    }

    /**
     * @internal
     */
    public function detachChild(AbstractWidget $child): void
    {
        $this->widgetTree->detach($child);
    }
}
