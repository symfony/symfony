<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html\Element;

use Symfony\Component\Tui\Html\ElementFactoryInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\DropdownWidget;
use Symfony\Component\Tui\Widget\RichTextWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;

/**
 * <select> with its <option>s: a list with size or multiple, a dropdown without.
 *
 * @experimental
 */
final class SelectElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $multiple = $element->hasAttribute('multiple');
        $items = [];
        $selected = null;

        // A customizable select: options holding elements, or a <selectedcontent> showing the chosen one
        $size = $element->getAttribute('size');
        $rich = !$multiple && !is_numeric($size) && (null !== $element->querySelector('selectedcontent') || null !== $element->querySelector('option *'));
        $contents = [];
        foreach ($element->querySelectorAll('option') as $option) {
            if (!$option instanceof \Dom\Element) {
                continue;
            }
            $group = 'optgroup' === strtolower($option->parentElement->localName ?? '') ? $option->parentElement : null;
            if ($option->hasAttribute('disabled') || $option->hasAttribute('hidden') || $group?->hasAttribute('disabled')) {
                continue;
            }
            $text = trim($builder->getText($option));
            $item = [
                'value' => $builder->getAttributeText($option, 'value') ?? $text,
                'label' => $builder->getAttributeText($option, 'label') ?? $text,
            ];
            if (null !== $group && null !== $label = $builder->getAttributeText($group, 'label')) {
                $item['group'] = $label;
            }
            if (null !== $description = $builder->getAttributeText($option, 'title')) {
                $item['description'] = $description;
            }
            if ($option->hasAttribute('selected')) {
                if ($multiple) {
                    $item['checked'] = true;
                } else {
                    $selected ??= \count($items);
                }
            }
            $items[] = $item;
            if ($rich) {
                $content = new RichTextWidget($builder->getInlineNodes($option));
                $builder->getStyleSheet()->describe($content, 'option', $builder->attributesOf($option));
                // Selected in the markup: :default, whatever is chosen since
                if ($option->hasAttribute('selected')) {
                    $content->setState('default');
                }
                $contents[] = $content;
            }
        }

        $list = new SelectListWidget($items, is_numeric($size) ? max(1, (int) $size) : min(max(1, \count($items)), 10), $multiple);
        if (null !== $selected) {
            $list->setSelectedIndex($selected);
        }
        FormControlStates::apply($list, $element, false);

        // Without size or multiple, a select is a closed list, like in a browser
        if (!$multiple && !is_numeric($size)) {
            // The states CSS matches (:required, :read-only...) are those of the <select>, the dropdown
            $dropdown = new DropdownWidget($list);
            // Its field is a part of it, like the inside of a <select> in browsers, that the button rules of the page
            // do not style; the <button> of a customizable select is the page's
            if (!array_any(array_values(array_filter(iterator_to_array($element->childNodes, false), static fn (\Dom\Node $node): bool => $node instanceof \Dom\Element)), static fn (\Dom\Element $child) => 'button' === strtolower($child->localName))) {
                $builder->getStyleSheet()->describe($dropdown->getField(), 'tui-select-field');
            }
            if ($contents) {
                $dropdown->setOptionContents($contents);
            }
            FormControlStates::apply($dropdown, $element, false);

            return $dropdown;
        }

        return $list;
    }
}
