<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Question;

/**
 * Represents a choice question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ChoiceQuestion extends Question
{
    private $choices;
    private $multiselect = false;
    private $errorMessage = 'Value "%s" is invalid';
    private $displayOptions = array('prompt' => ' > ');

    /**
     * {@inheritdoc}
     *
     * @param array $displayOptions
     *
     * Currently the following display options are supported:
     *
     * - focused_choice_template:    Used to display a focused choice when asking the question
     * - unfocused_choice_template:  Used to display an unfocused choice when asking the question
     * - selected_choice_template:   Used to display a selected choice when asking the question (multiselect only)
     * - deselected_choice_template: Used to display a deselected choice when asking the question (multiselect only)
     * - prompt:                     Used as prompt when asking the question (when stty is not available)
     */
    public function __construct($question, array $choices, $default = null, array $displayOptions = array())
    {
        parent::__construct($question, $default);

        $this->choices = $choices;
        $this->displayOptions = array_merge($this->displayOptions, $displayOptions);
        $this->setValidator($this->getDefaultValidator());
        $this->setAutocompleterValues(array_keys($choices));
    }

    /**
     * Returns available choices.
     *
     * @return array
     */
    public function getChoices()
    {
        return $this->choices;
    }

    /**
     * Sets multiselect option.
     *
     * When multiselect is set to true, multiple choices can be answered.
     *
     * @param bool    $multiselect
     *
     * @return ChoiceQuestion The current instance
     */
    public function setMultiselect($multiselect)
    {
        $this->multiselect = $multiselect;
        $this->setValidator($this->getDefaultValidator());

        return $this;
    }

    /**
     * Tells if the question is multi select.
     *
     * @return bool
     */
    public function isMultiselect()
    {
        return $this->multiselect;
    }

    /**
     * Gets the prompt for choices.
     *
     * @return string
     */
    public function getPrompt()
    {
        return $this->displayOptions['prompt'];
    }

    /**
     * Sets the prompt for choices.
     *
     * @param string $prompt
     *
     * @return ChoiceQuestion The current instance
     */
    public function setPrompt($prompt)
    {
        $this->displayOptions['prompt'] = $prompt;

        return $this;
    }

    /**
     * Sets the error message for invalid values.
     *
     * The error message has a string placeholder (%s) for the invalid value.
     *
     * @param string $errorMessage
     *
     * @return ChoiceQuestion The current instance
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
        $this->setValidator($this->getDefaultValidator());

        return $this;
    }

    /**
     * Gets a display option.
     *
     * @param string $name    The name of the option
     * @param mixed  $default The default value to return when not found
     *
     * @return mixed The option value
     */
    public function getDisplayOption($name, $default = null)
    {
        if (isset($this->displayOptions[$name])) {
            return $this->displayOptions[$name];
        }

        return $default;
    }

    private function getDefaultValidator()
    {
        $choices = $this->choices;
        $errorMessage = $this->errorMessage;
        $multiselect = $this->multiselect;

        return function ($selected) use ($choices, $errorMessage, $multiselect) {
            // Collapse all spaces.
            $selectedChoices = str_replace(' ', '', $selected);

            if ($multiselect) {
                // Check for a separated comma values
                if (!preg_match('/^[a-zA-Z0-9_-]+(?:,[a-zA-Z0-9_-]+)*$/', $selectedChoices, $matches)) {
                    throw new \InvalidArgumentException(sprintf($errorMessage, $selected));
                }
                $selectedChoices = explode(',', $selectedChoices);
            } else {
                $selectedChoices = array($selected);
            }

            $multiselectChoices = array();
            foreach ($selectedChoices as $value) {
                if (empty($choices[$value])) {
                    throw new \InvalidArgumentException(sprintf($errorMessage, $value));
                }
                array_push($multiselectChoices, $choices[$value]);
            }

            if ($multiselect) {
                return $multiselectChoices;
            }

            return $choices[$selected];
        };
    }
}
