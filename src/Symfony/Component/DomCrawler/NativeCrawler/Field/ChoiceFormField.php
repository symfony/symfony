<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\NativeCrawler\Field;

use Symfony\Component\DomCrawler\Field\ChoiceFormFieldTrait;

/**
 * ChoiceFormField represents a choice form field.
 *
 * It is constructed from an HTML select tag, or an HTML checkbox, or radio inputs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class ChoiceFormField extends FormField
{
    use ChoiceFormFieldTrait;

    /**
     * Adds a choice to the current ones.
     *
     * @throws \LogicException When choice provided is not multiple nor radio
     *
     * @internal
     */
    public function addChoice(\DOM\Element $node): void
    {
        if (!$this->multiple && 'radio' !== $this->type) {
            throw new \LogicException(\sprintf('Unable to add a choice for "%s" as it is not multiple or is not a radio button.', $this->name));
        }

        $option = $this->buildOptionValue($node);
        $this->options[] = $option;

        if ($node->hasAttribute('checked')) {
            $this->value = $option['value'];
        }
    }

    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        $nodeName = strtolower($this->node->nodeName);
        if ('input' !== $nodeName && 'select' !== $nodeName) {
            throw new \LogicException(\sprintf('A ChoiceFormField can only be created from an input or select tag (%s given).', $nodeName));
        }

        if ('input' === $nodeName && 'checkbox' !== strtolower($this->node->getAttribute('type')) && 'radio' !== strtolower($this->node->getAttribute('type'))) {
            throw new \LogicException(\sprintf('A ChoiceFormField can only be created from an input tag with a type of checkbox or radio (given type is "%s").', $this->node->getAttribute('type')));
        }

        $this->value = null;
        $this->options = [];
        $this->multiple = false;

        if ('input' == $nodeName) {
            $this->type = strtolower($this->node->getAttribute('type'));
            $optionValue = $this->buildOptionValue($this->node);
            $this->options[] = $optionValue;

            if ($this->node->hasAttribute('checked')) {
                $this->value = $optionValue['value'];
            }
        } else {
            $this->type = 'select';
            if ($this->node->hasAttribute('multiple')) {
                $this->multiple = true;
                $this->value = [];
                $this->name = str_replace('[]', '', $this->name);
            }

            $found = false;
            foreach ($this->node->childNodes as $option) {
                if ('option' !== strtolower($option->nodeName)) {
                    continue;
                }

                $optionValue = $this->buildOptionValue($option);
                $this->options[] = $optionValue;

                if ($option->hasAttribute('selected')) {
                    $found = true;
                    if ($this->multiple) {
                        $this->value[] = $optionValue['value'];
                    } else {
                        $this->value = $optionValue['value'];
                    }
                }
            }

            // if no option is selected and if it is a simple select box, take the first option as the value
            if (!$found && !$this->multiple && $this->options) {
                $this->value = $this->options[0]['value'];
            }
        }
    }

    /**
     * Returns option value with associated disabled flag.
     */
    private function buildOptionValue(\DOM\Element $node): array
    {
        $option = [];

        $defaultDefaultValue = 'select' === strtolower($this->node->nodeName) ? '' : 'on';
        $defaultValue = (isset($node->nodeValue) && $node->nodeValue) ? $node->nodeValue : $defaultDefaultValue;
        $option['value'] = $node->hasAttribute('value') ? $node->getAttribute('value') : $defaultValue;
        $option['disabled'] = $node->hasAttribute('disabled');

        return $option;
    }
}
