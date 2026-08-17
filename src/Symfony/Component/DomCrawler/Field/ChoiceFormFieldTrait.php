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
 * Holds the choice logic shared by the classic and the native choice fields.
 *
 * The using class declares initialize() and addChoice() with the element type it
 * accepts, so that each of them keeps an exact signature, and calls
 * initializeChoices() and attachChoice() to do the work.
 *
 * @internal
 */
trait ChoiceFormFieldTrait
{
    private string $type;
    private bool $multiple;
    private array $options;
    private bool $validationDisabled = false;

    /**
     * Returns true if the field should be included in the submitted values.
     *
     * @return bool true if the field should be included in the submitted values, false otherwise
     */
    public function hasValue(): bool
    {
        // don't send a value for unchecked checkboxes
        if (\in_array($this->type, ['checkbox', 'radio'], true) && null === $this->value) {
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

        foreach ($this->options as $option) {
            if ($option['value'] == $this->value && $option['disabled']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the value of the field.
     */
    public function select(string|array|bool $value): void
    {
        $this->setValue($value);
    }

    /**
     * Selects an option by its visible text content.
     *
     * The match is case-sensitive and performed after collapsing ASCII
     * whitespace sequences in both the input and the option text. The
     * `label` attribute of <option> elements is not honored; only the
     * textual content is considered.
     *
     * When several options share the same text, the first one wins.
     * Disabled options remain selectable, mirroring select().
     *
     * For `<select multiple>`, an array of texts can be passed to select
     * several options at once.
     *
     * @see self::normalizeWhitespace()
     *
     * @throws \LogicException           When the field is not a select
     * @throws \InvalidArgumentException When no option matches the given text
     */
    public function selectByText(string|array $text): void
    {
        if ('select' !== $this->type) {
            throw new \LogicException(\sprintf('You cannot call selectByText() on "%s" as it is not a select (%s).', $this->name, $this->type));
        }

        $texts = (array) $text;
        $values = [];
        foreach ($texts as $needle) {
            $values[] = $this->resolveTextToValue($needle);
        }

        $this->setValue(\is_array($text) ? $values : $values[0]);
    }

    /**
     * Ticks a checkbox.
     *
     * @throws \LogicException When the type provided is not correct
     */
    public function tick(): void
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(\sprintf('You cannot tick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }

        $this->setValue(true);
    }

    /**
     * Unticks a checkbox.
     *
     * @throws \LogicException When the type provided is not correct
     */
    public function untick(): void
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(\sprintf('You cannot untick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }

        $this->setValue(false);
    }

    /**
     * Sets the value of the field.
     *
     * @throws \InvalidArgumentException When value type provided is not correct
     */
    public function setValue(string|array|bool|null $value): void
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
            } else {
                parent::setValue($value);
            }
        }
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
     * Reads the type, the options and the initial value out of the node.
     */
    private function initializeChoices(): void
    {
        $this->value = null;
        $this->options = [];
        $this->multiple = false;

        if ('input' === $this->node->localName) {
            $this->type = strtolower($this->node->getAttribute('type') ?? '');
            $optionValue = $this->buildOptionValue($this->node);
            $this->options[] = $optionValue;

            if ($this->node->hasAttribute('checked')) {
                $this->value = $optionValue['value'];
            }

            return;
        }

        $this->type = 'select';
        if ($this->node->hasAttribute('multiple')) {
            $this->multiple = true;
            $this->value = [];
            $this->name = str_replace('[]', '', $this->name);
        }

        $found = false;
        foreach (self::collectDescendants($this->node, static fn ($node) => 'option' === $node->localName) as $option) {
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

    /**
     * Adds the given node to the current options.
     *
     * @throws \LogicException When choice provided is neither multiple, radio nor select,
     *                         or when the node tag does not match the field type
     */
    private function attachChoice(\DOMElement|\Dom\Element $node): void
    {
        if (!$this->multiple && !\in_array($this->type, ['radio', 'select'], true)) {
            throw new \LogicException(\sprintf('Unable to add a choice for "%s" as it is neither multiple, a radio button nor a select field (type is "%s").', $this->name, $this->type));
        }

        $expectedTag = 'select' === $this->type ? 'option' : 'input';
        if ($expectedTag !== $node->localName) {
            throw new \LogicException(\sprintf('Unable to add a choice for "%s": expected an "%s" tag, got "%s".', $this->name, $expectedTag, $node->localName));
        }

        $option = $this->buildOptionValue($node);
        $this->options[] = $option;

        if ($node->hasAttribute('select' === $this->type ? 'selected' : 'checked')) {
            if ($this->multiple) {
                $this->value[] = $option['value'];
            } else {
                $this->value = $option['value'];
            }
        }
    }

    /**
     * Collapses sequences of HTML5 ASCII whitespace into a single space and trims the result,
     * the same way Crawler::text() does, so the text passed by the caller matches what a user
     * sees. Non-ASCII whitespace, e.g. U+00A0 or U+2028, is left untouched.
     */
    private static function normalizeWhitespace(string $string): string
    {
        return trim(preg_replace("/(?:[ \n\r\t\x0C]{2,}+|[\n\r\t\x0C])/", ' ', $string), " \n\r\t\x0C");
    }

    private function resolveTextToValue(string $text): string
    {
        $needle = self::normalizeWhitespace($text);
        foreach ($this->options as $option) {
            if ($option['text'] === $needle) {
                return $option['value'];
            }
        }

        throw new \InvalidArgumentException(\sprintf('Input "%s" has no option with text "%s" (possible texts: "%s").', $this->name, $text, implode('", "', array_column($this->options, 'text'))));
    }

    /**
     * Returns option value, normalized text content and disabled flag.
     *
     * The text content is read instead of the node value, because the native DOM
     * reports a null node value for an element, as the DOM standard requires.
     */
    private function buildOptionValue(\DOMElement|\Dom\Element $node): array
    {
        $option = [];

        $defaultDefaultValue = 'select' === $this->node->localName ? '' : 'on';
        $defaultValue = $node->textContent ?: $defaultDefaultValue;
        $option['value'] = $node->hasAttribute('value') ? $node->getAttribute('value') : $defaultValue;
        $option['text'] = self::normalizeWhitespace($node->textContent);
        $option['disabled'] = $node->hasAttribute('disabled');

        return $option;
    }
}
