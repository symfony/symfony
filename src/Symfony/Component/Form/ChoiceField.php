<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ChoiceList\DefaultChoiceList;

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
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class ChoiceField extends HybridField
{
    protected $choiceList;

    public function __construct($name = null, array $options = array())
    {
        parent::__construct($name, $options);

        // until we have DI, this MUST happen after configure()
        if ($this->isExpanded()) {
            $this->setFieldMode(self::FORM);

            foreach ($this->choiceList->getPreferredChoices() as $choice => $value) {
                $this->add($this->newChoiceField($choice, $value));
            }

            foreach ($this->choiceList->getOtherChoices() as $choice => $value) {
                $this->add($this->newChoiceField($choice, $value));
            }
        } else {
            $this->setFieldMode(self::FIELD);
        }
    }

    protected function configure()
    {
        $this->addRequiredOption('choices');
        $this->addOption('preferred_choices', array());
        $this->addOption('multiple', false);
        $this->addOption('expanded', false);
        $this->addOption('empty_value', '');

        parent::configure();

        $this->choiceList = new DefaultChoiceList(
            $this->getOption('choices'),
            $this->getOption('preferred_choices'),
            $this->getOption('empty_value'),
            $this->isRequired()
        );
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
        return $this->choiceList->getPreferredChoices();
    }

    public function getOtherChoices()
    {
        return $this->choiceList->getOtherChoices();
    }

    public function getLabel($choice)
    {
        return $this->choiceList->getLabel($choice);
    }

    public function isChoiceGroup($choice)
    {
        return $this->choiceList->isChoiceGroup($choice);
    }

    public function isChoiceSelected($choice)
    {
        return $this->choiceList->isChoiceSelected($choice, $this->getDisplayedData());
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
            $choices = $this->choiceList->getChoices();

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
