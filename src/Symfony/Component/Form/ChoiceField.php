<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Renderer\SelectRenderer;
use Symfony\Component\Form\Renderer\InputRadioRenderer;
use Symfony\Component\Form\Renderer\InputCheckboxRenderer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;

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
        $this->addOption('translate_choices', false);

        if (count($this->getOption('preferred_choices')) > 0) {
            $this->preferredChoices = array_flip($this->getOption('preferred_choices'));

            if (false && $diff = array_diff_key($this->options, $this->knownOptions)) {
                //throw new InvalidOptionsException(sprintf('%s does not support the following options: "%s".', get_class($this), implode('", "', array_keys($diff))), array_keys($diff));
            }
        }

        if ($this->getOption('expanded')) {
            $this->setFieldMode(self::GROUP);

            $choices = $this->getOption('choices');

            foreach ($this->getOption('preferred_choices') as $choice) {
                $this->add($this->newChoiceField($choice, $choices[$choice]));
                unset($choices[$choice]);
            }

            foreach ($this->getOption('choices') as $choice => $value) {
                $this->add($this->newChoiceField($choice, $value));
            }
        } else {
            $this->setFieldMode(self::FIELD);
        }
    }

    /**
     * Returns a new field of type radio button or checkbox.
     *
     * @param string $key      The key for the option
     * @param string $label    The label for the option
     */
    protected function newChoiceField($choice, $label)
    {
        if ($this->getOption('multiple')) {
            return new CheckboxField($choice, array(
                'value' => $choice,
                'label' => $label,
                'translate_label' => $this->getOption('translate_choices'),
            ));
        } else {
            return new RadioField($choice, array(
                'value' => $choice,
                'label' => $label,
                'translate_label' => $this->getOption('translate_choices'),
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
        if (!$this->getOption('multiple') && $this->getOption('expanded')) {
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
        if ($this->getOption('expanded')) {
            $choices = $this->getOption('choices');

            foreach ($choices as $choice => $_) {
                $choices[$choice] = $this->getOption('multiple')
                    ? in_array($choice, (array)$value, true)
                    : ($choice === $value);
            }

            return $choices;
        } else {
            return parent::transform($value);
        }
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
        if ($this->getOption('expanded')) {
            $choices = array();

            foreach ($value as $choice => $selected) {
                if ($selected) {
                    $choices[] = $choice;
                }
            }

            if ($this->getOption('multiple')) {
                return $choices;
            } else {
                return count($choices) > 0 ? current($choices) : null;
            }
        } else {
            return parent::reverseTransform($value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        if ($this->getOption('expanded')) {
            $html = "";

            foreach ($this as $field) {
                $html .= $field->render()."\n";
            }

            return $html;
        } else {
            $attrs['id'] = $this->getId();
            $attrs['name'] = $this->getName();
            $attrs['disabled'] = $this->isDisabled();

            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            if ($this->getOption('multiple') && !$this->getOption('expanded')) {
                $attrs['name'] .= '[]';
            }

            if ($this->getOption('multiple')) {
                $attrs['multiple'] = 'multiple';
            }

            $selected = array_flip(array_map('strval', (array)$this->getDisplayedData()));
            $html = "\n";

            if (!$this->isRequired()) {
                $html .= $this->renderChoices(array('' => $this->getOption('empty_value')), $selected)."\n";
            }

            $choices = $this->getOption('choices');

            if (count($this->preferredChoices) > 0) {
                $html .= $this->renderChoices(array_intersect_key($choices, $this->preferredChoices), $selected)."\n";
                $html .= $this->generator->contentTag('option', $this->getOption('separator'), array('disabled' => true))."\n";
            }

            $html .= $this->renderChoices(array_diff_key($choices, $this->preferredChoices), $selected)."\n";

            return $this->generator->contentTag('select', $html, array_merge($attrs, $attributes));
        }
    }

    /**
     * Returns an array of option tags for the choice field
     *
     * @return array  An array of option tags
     */
    protected function renderChoices(array $choices, array $selected)
    {
        $options = array();

        foreach ($choices as $key => $option) {
            if (is_array($option)) {
                $options[] = $this->generator->contentTag(
                    'optgroup',
                    "\n".$this->renderChoices($option, $selected)."\n",
                    array('label' => $this->generator->escape($key))
                );
            } else {
                $attributes = array('value' => $this->generator->escape($key));

                if (isset($selected[strval($key)])) {
                    $attributes['selected'] = true;
                }

                if ($this->getOption('translate_choices')) {
                    $option = $this->translate($option);
                }

                $options[] = $this->generator->contentTag(
                    'option',
                    $this->generator->escape($option),
                    $attributes
                );
            }
        }

        return implode("\n", $options);
    }
}
