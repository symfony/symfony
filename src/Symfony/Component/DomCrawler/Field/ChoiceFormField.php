<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Field;

/**
 * ChoiceFormField represents a choice form field.
 *
 * It is constructed from an HTML select tag, or an HTML checkbox, or radio inputs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ChoiceFormField extends FormField
{
    private string $type;
    private bool $multiple;
    private array $options;
    private ?int $selectedOption = null;
    private array $selectedOptions = [];
    private bool $validationDisabled = false;
    private ?int $checkedRadio = null;

    /**
     * Returns true if the field should be included in the submitted values.
     *
     * @return bool true if the field should be included in the submitted values, false otherwise
     */
    public function hasValue(): bool
    {
        if (\in_array($this->type, ['checkbox', 'radio', 'select']) && null === $this->value) {
            return false;
        }

        return true;
    }

    /**
     * Check if the current selected option is disabled.
     */
    public function isDisabled(): bool
    {
        if ('checkbox' === $this->type) {
            return parent::isDisabled();
        }

        if (parent::isDisabled() && 'select' === $this->type) {
            return true;
        }

        // radios may share their value, so look at the checked one only
        if ('radio' === $this->type) {
            return null !== $this->checkedRadio && $this->options[$this->checkedRadio]['disabled'];
        }

        if ('select' === $this->type && !$this->multiple) {
            return null !== $this->selectedOption && $this->options[$this->selectedOption]['disabled'];
        }

        foreach ($this->options as $option) {
            if ($option['value'] == $this->value && $option['disabled']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the value of the field.
     *
     * @return void
     */
    public function select(string|array|bool $value)
    {
        $this->setValue($value);
    }

    /**
     * Ticks a checkbox.
     *
     * @return void
     *
     * @throws \LogicException When the type provided is not correct
     */
    public function tick()
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(\sprintf('You cannot tick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }

        $this->setValue(true);
    }

    /**
     * Unticks a checkbox.
     *
     * @return void
     *
     * @throws \LogicException When the type provided is not correct
     */
    public function untick()
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(\sprintf('You cannot untick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }

        $this->setValue(false);
    }

    /**
     * Sets the value of the field.
     *
     * @return void
     *
     * @throws \InvalidArgumentException When value type provided is not correct
     */
    public function setValue(string|array|bool|null $value)
    {
        if ('checkbox' === $this->type && false === $value) {
            // uncheck
            $this->value = null;
        } elseif ('checkbox' === $this->type && true === $value) {
            // check
            $this->value = $this->options[0]['value'];
        } else {
            if (\is_array($value)) {
                if (!$this->multiple) {
                    throw new \InvalidArgumentException(\sprintf('The value for "%s" cannot be an array.', $this->name));
                }

                foreach ($value as $v) {
                    if (!$this->containsOption($v, $this->options)) {
                        throw new \InvalidArgumentException(\sprintf('Input "%s" cannot take "%s" as a value (possible values: "%s").', $this->name, $v, implode('", "', $this->availableOptionValues())));
                    }
                }
            } elseif (!$this->containsOption($value, $this->options)) {
                throw new \InvalidArgumentException(\sprintf('Input "%s" cannot take "%s" as a value (possible values: "%s").', $this->name, $value, implode('", "', $this->availableOptionValues())));
            }

            if ($this->multiple) {
                $value = (array) $value;
            }

            if (\is_array($value)) {
                $this->value = $value;
                $this->selectedOptions = [];
                foreach ($value as $key => $v) {
                    foreach ($this->options as $i => $option) {
                        if ($option['value'] == $v && !\in_array($i, $this->selectedOptions, true)) {
                            $this->selectedOptions[$key] = $i;

                            break;
                        }
                    }
                }
            } else {
                parent::setValue($value);

                if ('radio' === $this->type) {
                    $this->checkRadio($this->value);
                } elseif ('select' === $this->type) {
                    $this->selectedOption = null;
                    foreach ($this->options as $i => $option) {
                        if ($option['value'] == $this->value) {
                            $this->selectedOption = $i;

                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Adds a choice to the current ones.
     *
     * @throws \LogicException When choice provided is not multiple nor radio
     *
     * @internal
     */
    public function addChoice(\DOMElement $node): void
    {
        if (!$this->multiple && 'radio' !== $this->type) {
            throw new \LogicException(\sprintf('Unable to add a choice for "%s" as it is not multiple or is not a radio button.', $this->name));
        }

        $option = $this->buildOptionValue($node);
        $this->options[] = $option;

        if ($node->hasAttribute('checked')) {
            if ($this->multiple) {
                $this->value[] = $option['value'];
                $this->selectedOptions[array_key_last($this->value)] = array_key_last($this->options);
            } else {
                $this->value = $option['value'];
                $this->checkedRadio = array_key_last($this->options);
            }
        }
    }

    /**
     * Returns the value to submit, without the values of the selected options that are disabled.
     *
     * @internal
     */
    public function getSubmittedValue(): string|array|null
    {
        $value = $this->getValue();

        if (!$this->multiple) {
            return $value;
        }

        $isList = array_is_list($value);
        foreach ($this->selectedOptions as $key => $i) {
            if ($this->options[$i]['disabled']) {
                unset($value[$key]);
            }
        }

        return $isList ? array_values($value) : $value;
    }

    /**
     * Returns the type of the choice field (radio, select, or checkbox).
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns true if the field accepts multiple values.
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Initializes the form field.
     *
     * @return void
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize()
    {
        if ('input' !== $this->node->nodeName && 'select' !== $this->node->nodeName) {
            throw new \LogicException(\sprintf('A ChoiceFormField can only be created from an input or select tag (%s given).', $this->node->nodeName));
        }

        if ('input' === $this->node->nodeName && 'checkbox' !== strtolower($this->node->getAttribute('type')) && 'radio' !== strtolower($this->node->getAttribute('type'))) {
            throw new \LogicException(\sprintf('A ChoiceFormField can only be created from an input tag with a type of checkbox or radio (given type is "%s").', $this->node->getAttribute('type')));
        }

        $this->value = null;
        $this->selectedOption = null;
        $this->selectedOptions = [];
        $this->options = [];
        $this->multiple = false;
        $this->checkedRadio = null;

        if ('input' == $this->node->nodeName) {
            $this->type = strtolower($this->node->getAttribute('type'));
            $optionValue = $this->buildOptionValue($this->node);
            $this->options[] = $optionValue;

            if ($this->node->hasAttribute('checked')) {
                $this->value = $optionValue['value'];
                $this->checkedRadio = 'radio' === $this->type ? 0 : null;
            }
        } else {
            $this->type = 'select';
            if ($this->node->hasAttribute('multiple')) {
                $this->multiple = true;
                $this->value = [];
                $this->name = str_replace('[]', '', $this->name);
            }

            $found = false;
            foreach ($this->xpath->query('descendant::option', $this->node) as $option) {
                $optionValue = $this->buildOptionValue($option);
                $this->options[] = $optionValue;

                if ($option->hasAttribute('selected')) {
                    $found = true;
                    if ($this->multiple) {
                        $this->value[] = $optionValue['value'];
                        $this->selectedOptions[] = \count($this->options) - 1;
                    } else {
                        $this->value = $optionValue['value'];
                        $this->selectedOption = \count($this->options) - 1;
                    }
                }
            }

            // if no option is selected and if it is a simple select box, take the first option as the value
            if (!$found && !$this->multiple && $this->options) {
                if (preg_match('/^[\t\n\f\r ]*\+?([0-9]+)/', $this->node->getAttribute('size'), $matches) && 1 < $matches[1]) {
                    return;
                }

                $this->value = $this->options[0]['value'];
                $this->selectedOption = 0;

                foreach ($this->options as $i => $option) {
                    if (!$option['disabled']) {
                        $this->value = $option['value'];
                        $this->selectedOption = $i;

                        break;
                    }
                }
            }
        }
    }

    /**
     * Returns option value with associated disabled flag.
     */
    private function buildOptionValue(\DOMElement $node): array
    {
        $option = [];

        $defaultDefaultValue = 'select' === $this->node->nodeName ? '' : 'on';
        $text = $node->nodeValue ?? '';
        if ('option' === $node->nodeName && !$node->hasAttribute('value')) {
            $text = '';
            $xpath = $node->ownerDocument === $this->node->ownerDocument ? $this->xpath : new \DOMXPath($node->ownerDocument);
            foreach ($xpath->query('descendant::text()[not(ancestor::*[local-name()="script" and (namespace-uri()="" or namespace-uri()="http://www.w3.org/1999/xhtml" or namespace-uri()="http://www.w3.org/2000/svg")])]', $node) as $textNode) {
                $text .= $textNode->nodeValue;
            }
        }
        // like browsers, use the option text with ASCII whitespace stripped and collapsed
        $text = trim(preg_replace('/[ \t\n\f\r]+/', ' ', $text), " \t\n\f\r");
        $defaultValue = '' !== $text ? $text : $defaultDefaultValue;
        $option['value'] = $node->hasAttribute('value') ? $node->getAttribute('value') : $defaultValue;
        $option['disabled'] = $node->hasAttribute('disabled') || ('option' === $node->nodeName && $node->parentNode instanceof \DOMElement && 'optgroup' === $node->parentNode->nodeName && $node->parentNode->hasAttribute('disabled')) || ('input' === $node->nodeName && $this->isInDisabledFieldset($node));

        return $option;
    }

    /**
     * Checks whether given value is in the existing options.
     *
     * @internal
     */
    public function containsOption(string $optionValue, array $options): bool
    {
        if ($this->validationDisabled) {
            return true;
        }

        foreach ($options as $option) {
            if ($option['value'] == $optionValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns list of available field options.
     *
     * @internal
     */
    public function availableOptionValues(): array
    {
        $values = [];

        foreach ($this->options as $option) {
            $values[] = $option['value'];
        }

        return $values;
    }

    /**
     * Disables the internal validation of the field.
     *
     * @internal
     *
     * @return $this
     */
    public function disableValidation(): static
    {
        $this->validationDisabled = true;

        return $this;
    }

    /**
     * Checks the first enabled radio with the given value, or the first one with that value if all are disabled.
     */
    private function checkRadio(string $value): void
    {
        $this->checkedRadio = null;

        foreach ($this->options as $i => $option) {
            if ($option['value'] == $value) {
                $this->checkedRadio ??= $i;

                if (!$option['disabled']) {
                    $this->checkedRadio = $i;

                    return;
                }
            }
        }
    }
}
