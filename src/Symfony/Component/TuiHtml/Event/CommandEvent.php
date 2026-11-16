<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Event;

use Symfony\Component\Tui\Event\AbstractEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Dispatched on the element a command is for, like the command event of HTML: a button with commandfor and
 * command was pressed, or HtmlView::invoke() was called.
 *
 * Built-in commands (show-modal, close, request-close, show-popover, hide-popover, toggle-popover) run after
 * the event unless its propagation is stopped; custom commands, whose names start with two dashes (--next),
 * only dispatch it, for the application to act on.
 *
 * @experimental
 */
class CommandEvent extends AbstractEvent
{
    public function __construct(AbstractWidget $target, private readonly string $command, private readonly ?AbstractWidget $source = null)
    {
        parent::__construct($target);
    }

    /**
     * The command, lowercased when it is a built-in one, as written when it is a custom one.
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * The button that invoked the command, if any.
     */
    public function getSource(): ?AbstractWidget
    {
        return $this->source;
    }
}
