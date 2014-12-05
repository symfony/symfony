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
    private $prompt = ' > ';
    private $errorMessage = 'Value "%s" is invalid';

    /**
     * Constructor.
     *
     * @param string $question The question to ask to the user
     * @param array  $choices  The list of available choices
     * @param mixed  $default  The default answer to return
     */
    public function __construct($question, array $choices, $default = null)
    {
        parent::__construct($question, $default);

        $this->choices = $choices;
        $this->setValidator($this->getDefaultValidator());
        $this->setAutocompleterValues($choices);
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
     * @param bool $multiselect
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
     * Gets the prompt for choices.
     *
     * @return string
     */
    public function getPrompt()
    {
        return $this->prompt;
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
        $this->prompt = $prompt;

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
     * Returns the default answer validator.
     *
     * @return callable
     */
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
                $this->checkMultipleResults($choices, $value);
                $result = $this->getResult($choices, $value);

                if (empty($result)) {
                    throw new \InvalidArgumentException(sprintf($errorMessage, $value));
                }
                array_push($multiselectChoices, $result);
            }

            if ($multiselect) {
                return $multiselectChoices;
            }

            return current($multiselectChoices);
        };
    }

    /**
     * Checks if there are multiple keys corresponding to the value supplied
     * by the user.
     *
     * @param array  $possibleChoices Possible value(s) that the user can supply
     * @param string $value           Value supplied by the user
     *
     * @return null
     */
    private function checkMultipleResults(array $possibleChoices, $value)
    {
        $results = array();
        foreach ($possibleChoices as $key => $choice) {
            if ($choice === $value) {
                $results[] = $key;
            }
        }

        if (count($results) > 1) {
            throw new \InvalidArgumentException(sprintf('The provided answer is ambiguous. Value should be one of %s.', implode(' or ', $results)));
        }
    }

    /**
     * Get the result according to what have been provided by the user and the
     * possible choices.
     *
     * @param array $possibleChoices Possible value(s) that the user can supply
     * @param string $value          Value supplied by the user
     *
     * @retun string
     */
    private function getResult(array $possibleChoices, $value)
    {
        $result = array_search($value, $possibleChoices);

        if (!$this->isAssoc($possibleChoices)) {
            if (!empty($result)) {
                $result = $possibleChoices[$result];
            } elseif (isset($possibleChoices[$value])) {
                $result = $possibleChoices[$value];
            }
        } elseif (empty($result) && array_key_exists($value, $possibleChoices)) {
            $result = $value;
        }

        return $result;
    }
}
