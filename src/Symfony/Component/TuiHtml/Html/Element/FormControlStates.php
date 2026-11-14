<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Html\Element;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;

/**
 * Sets the states behind :required/:optional and :read-only/:read-write.
 *
 * @experimental
 *
 * @internal
 */
final class FormControlStates
{
    /**
     * @param bool|null $editable Whether the value can be typed in, null when the control holds no text
     */
    public static function apply(AbstractWidget $widget, \Dom\Element $element, ?bool $editable): void
    {
        $widget->setState($element->hasAttribute('required') ? 'required' : 'optional');
        if (null !== $editable) {
            $widget->setState($editable && !HtmlBuilder::isDisabled($element) ? 'read-write' : 'read-only');
        }
    }
}
