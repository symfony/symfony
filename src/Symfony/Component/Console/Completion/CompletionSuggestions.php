<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Completion;

use Symfony\Component\Console\Input\InputOption;

/**
 * Stores all completion suggestions for the current input.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class CompletionSuggestions
{
    private $valueSuggestions = [];
    private $optionSuggestions = [];

    /**
     * Add a suggested value for an input option or argument.
     *
     * @return $this
     */
    public function suggestValue(string $value): self
    {
        $this->valueSuggestions[] = $value;

        return $this;
    }

    /**
     * Add multiple suggested values at once for an input option or argument.
     *
     * @param string[] $values
     *
     * @return $this
     */
    public function suggestValues(array $values): self
    {
        $this->valueSuggestions = array_merge($this->valueSuggestions, $values);

        return $this;
    }

    /**
     * Add a suggestion for an input option name.
     *
     * @return $this
     */
    public function suggestOption(InputOption $option): self
    {
        $this->optionSuggestions[] = $option;

        return $this;
    }

    /**
     * Add multiple suggestions for input option names at once.
     *
     * @param InputOption[] $options
     *
     * @return $this
     */
    public function suggestOptions(array $options): self
    {
        foreach ($options as $option) {
            $this->suggestOption($option);
        }

        return $this;
    }

    /**
     * @return InputOption[]
     */
    public function getOptionSuggestions(): array
    {
        return $this->optionSuggestions;
    }

    /**
     * @return string[]
     */
    public function getValueSuggestions(): array
    {
        return $this->valueSuggestions;
    }
}
