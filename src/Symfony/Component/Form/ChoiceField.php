<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\InvalidOptionsException;

/**
 * Lets the user select between different choices.
 *
 * Available options:
 *
 *  * choices:              An array of key-value pairs that will represent the choices
 *  * preferred_choices:    An array of choices (by key) that should be displayed
 *                          above all other options in the field
 *  * empty_value:          If set to a non-false value, an "empty" option will
 *                          be added to the top of the countries choices. A
 *                          common value might be "Choose a country". Default: false.
 *
 * The multiple and expanded options control exactly which HTML element
 * that should be used to render this field:
 *
 *  * expanded = false, multiple = false    A drop-down select element
 *  * expanded = false, multiple = true     A multiple select element
 *  * expanded = true, multiple = false     A series of input radio elements
 *  * expanded = true, multiple = true      A series of input checkboxes
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ChoiceField extends HybridField
{
    /**
     * Stores the preferred choices with the choices as keys
     * @var array
     */
    protected $preferredChoices = array();

    /**
     * Stores the choices
     * You should only access this property through getChoices()
     * @var array
     */
    private $choices = array();

    protected function configure()
    {
        $this->addRequiredOption('choices');
        $this->addOption('preferred_choices', array());
        $this->addOption('multiple', false);
        $this->addOption('expanded', false);
        $this->addOption('empty_value', '');

        parent::configure();

        $choices = $this->getOption('choices');

        if (!is_array($choices) && !$choices instanceof \Closure) {
            throw new InvalidOptionsException('The choices option must be an array or a closure', array('choices'));
        }

        if (!is_array($this->getOption('preferred_choices'))) {
            throw new InvalidOptionsException('The preferred_choices option must be an array', array('preferred_choices'));
        }

        if (count($this->getOption('preferred_choices')) > 0) {
            $this->preferredChoices = array_flip($this->getOption('preferred_choices'));
        }

        if ($this->isExpanded()) {
            $this->setFieldMode(self::FORM);

            $choices = $this->getChoices();

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

    /**
     * Initializes the choices
     *
     * If the choices were given as a closure, the closure is executed now.
     *
     * @return array
     */
    protected function initializeChoices()
    {
        if (!$this->choices) {
            $this->choices = $this->getInitializedChoices();

            if (!$this->isRequired()) {
                $this->choices = array('' => $this->getOption('empty_value')) + $this->choices;
            }
        }
    }

    protected function getInitializedChoices()
    {
        $choices = $this->getOption('choices');

        if ($choices instanceof \Closure) {
            $choices = $choices->__invoke();
        }

        if (!is_array($choices)) {
            throw new InvalidOptionsException('The "choices" option must be an array or a closure returning an array', array('choices'));
        }

        return $choices;
    }

    /**
     * Returns the choices
     *
     * If the choices were given as a closure, the closure is executed on
     * the first call of this method.
     *
     * @return array
     */
    protected function getChoices()
    {
        $this->initializeChoices();

        return $this->choices;
    }

    public function getPreferredChoices()
    {
        return array_intersect_key($this->getChoices(), $this->preferredChoices);
    }

    public function getOtherChoices()
    {
        return array_diff_key($this->getChoices(), $this->preferredChoices);
    }

    public function getLabel($choice)
    {
        $choices = $this->getChoices();

        return isset($choices[$choice]) ? $choices[$choice] : null;
    }

    public function isChoiceGroup($choice)
    {
        return is_array($choice) || $choice instanceof \Traversable;
    }

    public function isChoiceSelected($choice)
    {
        return in_array((string) $choice, (array) $this->getDisplayedData(), true);
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
     * @param string $choice   The key for the option
     * @param string $label    The label for the option
     */
    protected function newChoiceField($choice, $label)
    {
        if ($this->isMultipleChoice()) {
            return new CheckboxField($choice, array(
                'value' => $choice,
            ));
        }

        return new RadioField($choice, array(
            'value' => $choice,
        ));
    }

    /**
     * {@inheritDoc}
     *
     * Takes care of converting the input from a single radio button
     * to an array.
     */
    public function submit($value)
    {
        if (!$this->isMultipleChoice() && $this->isExpanded()) {
            $value = null === $value ? array() : array($value => true);
        }

        parent::submit($value);
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
            $choices = $this->getChoices();

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
                $value = count($choices) > 0 ? current($choices) : null;
            }
        }

        return parent::reverseTransform($value);
    }
}
