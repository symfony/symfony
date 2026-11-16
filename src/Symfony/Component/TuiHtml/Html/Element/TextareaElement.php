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
use Symfony\Component\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Widget\EditorWidget;

/**
 * Multi-line text inputs.
 *
 * @experimental
 */
final class TextareaElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $editor = new EditorWidget();
        // Like browsers, drop the newline right after the opening tag
        $editor->setText(preg_replace('/^\r?\n/', '', $element->textContent));
        // rows: that many lines, scrolling past them like a browser's, unless field-sizing: content lets it grow;
        // without it, it grows with its content
        if (is_numeric($rows = $element->getAttribute('rows'))) {
            $editor->setMinVisibleLines(max(1, min(1000, (int) $rows)));
            $editor->setMaxVisibleLines(max(1, min(1000, (int) $rows)));
        }
        if (null !== $placeholder = $builder->getAttributeText($element, 'placeholder')) {
            $editor->setPlaceholder($placeholder);
        }
        $editor->setReadOnly($element->hasAttribute('readonly'));
        if (ctype_digit(trim($element->getAttribute('maxlength') ?? ''))) {
            $editor->setMaxLength((int) trim($element->getAttribute('maxlength') ?? ''));
        }
        FormControlStates::apply($editor, $element, !$editor->isReadOnly());
        ConstraintValidation::text($editor, $element, 'textarea', $editor->getText(...));

        return $editor;
    }
}
