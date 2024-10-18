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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
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
        if (\in_array($this->type, ['checkbox', 'radio']) && null === $this->value) {
            return false;
        }

        return true;
    }

    /**
     * Check if the current selected option is disabled.
     */
    public function isDisabled(): bool
    {
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
}
