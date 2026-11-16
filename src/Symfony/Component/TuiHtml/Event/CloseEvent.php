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
 * Dispatched on a dialog when it closes, like the close event of HTML, with its return value.
 *
 * @experimental
 */
class CloseEvent extends AbstractEvent
{
    public function __construct(AbstractWidget $target, private readonly string $returnValue = '')
    {
        parent::__construct($target);
    }

    /**
     * The return value of the dialog, like HTMLDialogElement::returnValue: what close() was given, or the value of
     * the button that submitted a form of method dialog in it.
     */
    public function getReturnValue(): string
    {
        return $this->returnValue;
    }
}
