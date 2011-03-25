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
 * It is constructed from a HTML select tag, or a HTML checkbox, or radio inputs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class ChoiceFormField extends FormField
{
    private $type;
    private $multiple;
    private $options;

    /**
     * Returns true if the field should be included in the submitted values.
     *
     * @return Boolean true if the field should be included in the submitted values, false otherwise
     */
    public function hasValue()
    {
        // don't send a value for unchecked checkboxes
        if (in_array($this->type, array('checkbox', 'radio')) && null === $this->value) {
            return false;
        }

        return true;
    }

    /**
     * Sets the value of the field.
     *
     * @param string $value The value of the field
     *
     * @throws \InvalidArgumentException When value type provided is not correct
     */
    public function select($value)
    {
        $this->setValue($value);
    }

    /**
     * Ticks a checkbox.
     *
     * @throws \InvalidArgumentException When value type provided is not correct
     *
     * @api
     */
    public function tick()
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(sprintf('You cannot tick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }

        $this->setValue(true);
    }

    /**
     * Ticks a checkbox.
     *
     * @throws \InvalidArgumentException When value type provided is not correct
     *
     * @api
     */
    public function untick()
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(sprintf('You cannot tick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }

        $this->setValue(false);
    }

    /**
     * Sets the value of the field.
     *
     * @param string $value The value of the field
     *
     * @throws \InvalidArgumentException When value type provided is not correct
     */
    public function setValue($value)
    {
        if ('checkbox' == $this->type && false === $value) {
            // uncheck
            $this->value = null;
        } elseif ('checkbox' == $this->type && true === $value) {
            // check
            $this->value = $this->options[0];
        } else {
            if (is_array($value)) {
                if (!$this->multiple) {
                    throw new \InvalidArgumentException(sprintf('The value for "%s" cannot be an array.', $this->name));
                }

                foreach ($value as $v) {
                    if (!in_array($v, $this->options)) {
                        throw new \InvalidArgumentException(sprintf('Input "%s" cannot take "%s" as a value (possible values: %s).', $this->name, $v, implode(', ', $this->options)));
                    }
                }
            } elseif (!in_array($value, $this->options)) {
                throw new \InvalidArgumentException(sprintf('Input "%s" cannot take "%s" as a value (possible values: %s).', $this->name, $value, implode(', ', $this->options)));
            }

            if ($this->multiple && !is_array($value)) {
                $value = array($value);
            }

            if (is_array($value)) {
                $this->value = $value;
            } else {
                parent::setValue($value);
            }
        }
    }

    /**
     * Adds a choice to the current ones.
     *
     * This method should only be used internally.
     *
     * @param \DOMNode $node A \DOMNode
     *
     * @throws \LogicException When choice provided is not multiple nor radio
     */
    public function addChoice(\DOMNode $node)
    {
        if (!$this->multiple && 'radio' != $this->type) {
            throw new \LogicException(sprintf('Unable to add a choice for "%s" as it is not multiple or is not a radio button.', $this->name));
        }

        $this->options[] = $value = $node->hasAttribute('value') ? $node->getAttribute('value') : '1';

        if ($node->getAttribute('checked')) {
            $this->value = $value;
        }
    }

    /**
     * Returns the type of the choice field (radio, select, or checkbox).
     *
     * @return string The type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns true if the field accepts multiple values.
     *
     * @return Boolean true if the field accepts multiple values, false otherwise
     */
    public function isMultiple()
    {
        return $this->multiple;
    }

    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize()
    {
        if ('input' != $this->node->nodeName && 'select' != $this->node->nodeName) {
            throw new \LogicException(sprintf('A ChoiceFormField can only be created from an input or select tag (%s given).', $this->node->nodeName));
        }

        if ('input' == $this->node->nodeName && 'checkbox' != $this->node->getAttribute('type') && 'radio' != $this->node->getAttribute('type')) {
            throw new \LogicException(sprintf('A ChoiceFormField can only be created from an input tag with a type of checkbox or radio (given type is %s).', $this->node->getAttribute('type')));
        }

        $this->value = null;
        $this->options = array();
        $this->multiple = false;

        if ('input' == $this->node->nodeName) {
            $this->type = $this->node->getAttribute('type');
            $this->options[] = $value = $this->node->hasAttribute('value') ? $this->node->getAttribute('value') : '1';

            if ($this->node->getAttribute('checked')) {
                $this->value = $value;
            }
        } else {
            $this->type = 'select';
            if ($this->node->hasAttribute('multiple')) {
                $this->multiple = true;
                $this->value = array();
                $this->name = str_replace('[]', '', $this->name);
            }

            $found = false;
            foreach ($this->xpath->query('descendant::option', $this->node) as $option) {
                $this->options[] = $option->getAttribute('value');

                if ($option->getAttribute('selected')) {
                    $found = true;
                    if ($this->multiple) {
                        $this->value[] = $option->getAttribute('value');
                    } else {
                        $this->value = $option->getAttribute('value');
                    }
                }
            }

            // if no option is selected and if it is a simple select box, take the first option as the value
            $option = $this->xpath->query('descendant::option', $this->node)->item(0);
            if (!$found && !$this->multiple && $option instanceof \DOMElement) {
                $this->value = $option->getAttribute('value');
            }
        }
    }
}
