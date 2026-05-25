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
use Symfony\Component\Tui\Focus\FocusManager;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;

/**
 * Internal widget tree manager.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class WidgetTree
{
    private WidgetContext $context;
    private readonly TerminalInterface $terminal;
    private ?AbstractWidget $root = null;

    public function __construct(
        Tui $tui,
        Keybindings $keybindings,
        FocusManager $focusManager,
        Renderer $renderer,
        TerminalInterface $terminal,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->terminal = $terminal;
        $this->context = new WidgetContext(
            $tui,
            $keybindings,
            $this->terminal,
            $focusManager,
            $renderer,
            $this,
            $eventDispatcher,
        );
    }

    public function setRoot(AbstractWidget $root): void
    {
        if ($this->root === $root) {
            return;
        }

        if (null !== $this->root) {
            $this->detach($this->root);
        }

        $this->root = $root;
        $this->attach($root, null);
    }

    public function attach(AbstractWidget $widget, ?AbstractWidget $parent): void
    {
        $widget->attach($parent, $this->context);

        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                $this->attach($child, $widget);
            }
        }
    }

    public function detach(AbstractWidget $widget): void
    {
        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                $this->detach($child);
            }
        }

        $cleanup = $widget->collectTerminalCleanupSequence();
        $widget->detach();

        if ('' !== $cleanup) {
            $this->terminal->write($cleanup);
        }
    }
}
