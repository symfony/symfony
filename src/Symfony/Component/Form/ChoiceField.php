<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Lets the user select between different choices
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class ChoiceField extends HybridField
{
    /**
     * Stores the preferred choices with the choices as keys
     * @var array
     */
    protected $preferredChoices = array();

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addRequiredOption('choices');
        $this->addOption('preferred_choices', array());
        $this->addOption('separator', '----------');
        $this->addOption('multiple', false);
        $this->addOption('expanded', false);
        $this->addOption('empty_value', '');

        if (!is_array($this->getOption('choices'))) {
            throw new UnexpectedTypeException('The choices option must be an array');
        }

        if (!is_array($this->getOption('preferred_choices'))) {
            throw new UnexpectedTypeException('The preferred_choices option must be an array');
        }

        if (count($this->getOption('preferred_choices')) > 0) {
            $this->preferredChoices = array_flip($this->getOption('preferred_choices'));
        }

        if ($this->isExpanded()) {
            $this->setFieldMode(self::GROUP);

            $choices = $this->getOption('choices');

            foreach ($this->preferredChoices as $choice => $_) {
                $this->add($this->newChoiceField($choice, $choices[$choice]));
            }

            foreach ($choices as $choice => $value) {
                if (!isset($this->preferredChoices[$choice])) {
                    $this->add($this->newChoiceField($choice, $value));
                }
            }
        } else {
            $this->setFieldMode(self::FIELD);
        }

        parent::configure();
    }

    public function getName()
    {
        // TESTME
        $name = parent::getName();

        // Add "[]" to the name in case a select tag with multiple options is
        // displayed. Otherwise only one of the selected options is sent in the
        // POST request.
        if ($this->isMultipleChoice() && !$this->isExpanded()) {
            $name .= '[]';
        }

        return $name;
    }

    public function getPreferredChoices()
    {
        return array_intersect_key($this->getOption('choices'), $this->preferredChoices);
    }

    public function getOtherChoices()
    {
        return array_diff_key($this->getOption('choices'), $this->preferredChoices);
    }

    public function getEmptyValue()
    {
        return $this->isRequired() ? false : $this->getOption('empty_value');
    }

    public function getLabel($choice)
    {
        $choices = $this->getOption('choices');

        return isset($choices[$choice]) ? $choices[$choice] : null;
    }

    public function isChoiceGroup($choice)
    {
        return is_array($choice) || $choice instanceof \Traversable;
    }

    public function isChoiceSelected($choice)
    {
        return in_array($choice, (array) $this->getDisplayedData());
    }

    public function isMultipleChoice()
    {
        return $this->getOption('multiple');
    }

    public function isExpanded()
    {
        return $this->getOption('expanded');
    }

    /**
     * Returns a new field of type radio button or checkbox.
     *
     * @param string $key      The key for the option
     * @param string $label    The label for the option
     */
    protected function newChoiceField($choice, $label)
    {
        if ($this->isMultipleChoice()) {
            return new CheckboxField($choice, array(
                'value' => $choice,
            ));
        } else {
            return new RadioField($choice, array(
                'value' => $choice,
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * Takes care of converting the input from a single radio button
     * to an array.
     */
    public function bind($value)
    {
        if (!$this->isMultipleChoice() && $this->isExpanded()) {
            $value = $value === null ? array() : array($value => true);
        }

        parent::bind($value);
    }

    /**
     * Transforms a single choice or an array of choices to a format appropriate
     * for the nested checkboxes/radio buttons.
     *
     * The result is an array with the options as keys and true/false as values,
     * depending on whether a given option is selected. If this field is rendered
     * as select tag, the value is not modified.
     *
     * @param  mixed $value  An array if "multiple" is set to true, a scalar
     *                       value otherwise.
     * @return mixed         An array if "expanded" or "multiple" is set to true,
     *                       a scalar value otherwise.
     */
    protected function transform($value)
    {
        if ($this->isExpanded()) {
            $value = parent::transform($value);
            $choices = $this->getOption('choices');

            foreach ($choices as $choice => $_) {
                $choices[$choice] = $this->isMultipleChoice()
                    ? in_array($choice, (array)$value, true)
                    : ($choice === $value);
            }

            return $choices;
        }
        return parent::transform($value);
    }

    /**
     * Transforms a checkbox/radio button array to a single choice or an array
     * of choices.
     *
     * The input value is an array with the choices as keys and true/false as
     * values, depending on whether a given choice is selected. The output
     * is an array with the selected choices or a single selected choice.
     *
     * @param  mixed $value  An array if "expanded" or "multiple" is set to true,
     *                       a scalar value otherwise.
     * @return mixed $value  An array if "multiple" is set to true, a scalar
     *                       value otherwise.
     */
    protected function reverseTransform($value)
    {
        if ($this->isExpanded()) {
            $choices = array();

            foreach ($value as $choice => $selected) {
                if ($selected) {
                    $choices[] = $choice;
                }
            }

            if ($this->isMultipleChoice()) {
                $value = $choices;
            } else {
                $value =  count($choices) > 0 ? current($choices) : null;
            }
        }
        return parent::reverseTransform($value);
    }
}
