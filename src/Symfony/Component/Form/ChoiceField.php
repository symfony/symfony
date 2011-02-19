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
use Symfony\Component\Form\ValueTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;

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
        $this->addOption('choices', array());
        $this->addOption('preferred_choices', array());
        $this->addOption('multiple', false);
        $this->addOption('expanded', false);
        $this->addOption('choice_list');

        parent::configure();

        if ($this->getOption('choice_list')) {
            $this->choiceList = $this->getOption('choice_list');
        } else {
            $this->choiceList = new DefaultChoiceList(
                $this->getOption('choices'),
                $this->getOption('preferred_choices')
            );
        }

        if ($this->getOption('expanded')) {
            if ($this->getOption('multiple')) {
                $this->setValueTransformer(new ArrayToChoicesTransformer($this->choiceList));
            } else {
                $this->setValueTransformer(new ScalarToChoicesTransformer($this->choiceList));
            }
        }
    }

    // TODO remove me again
    public function getChoiceList()
    {
        return $this->choiceList;
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
}
