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

use Symfony\Component\Console\Exception\InvalidArgumentException;

use function Symfony\Component\String\s;

/**
 * Represents a choice question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ChoiceQuestion extends Question
{
    private bool $multiselect = false;
    private string $prompt = ' > ';
    private string $errorMessage = 'Value "%s" is invalid';
    private bool $usesEnum = false;

    /**
     * @var array<string|bool|int|float|\Stringable|\UnitEnum>
     */
    private array $choices = [];

    /**
     * @var (\Closure(\UnitEnum):string)|null
     */
    private ?\Closure $customEnumRender = null;

    /**
     * @param string                                                           $question      The question to ask to the user
     * @param array<string|bool|int|float|\Stringable>|class-string<\UnitEnum> $choicesOrEnum The list of available choices or an Enum FQCN
     * @param string|bool|int|float|\UnitEnum|null                             $default       The default answer to return
     */
    public function __construct(
        string $question,
        array|string $choicesOrEnum,
        string|bool|int|float|\UnitEnum|null $default = null,
    ) {
        if (!$choicesOrEnum) {
            throw new \LogicException('Choice question must have at least 1 choice available.');
        } elseif (\is_string($choicesOrEnum) && !enum_exists($choicesOrEnum)) {
            throw new \LogicException(\sprintf('Enum "%s" does not exist.', $choicesOrEnum));
        } elseif (\is_string($choicesOrEnum) && 0 === \count($choicesOrEnum::cases())) {
            throw new \LogicException('Choice question must have at least 1 choice available.');
        } elseif (\is_string($choicesOrEnum) && null !== $default && !($default instanceof $choicesOrEnum)) {
            throw new \LogicException('Default value does not exist in the enum.');
        }

        parent::__construct($question, $default);

        $this->usesEnum = \is_string($choicesOrEnum);
        $this->choices = $this->usesEnum ? $choicesOrEnum::cases() : $choicesOrEnum;
        $this->setValidator($this->getDefaultValidator());
        $this->setAutocompleterValues($this->choices);
    }

    /**
     * @return array<string|bool|int|float|\Stringable|\UnitEnum>
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Sets multiselect option.
     *
     * When multiselect is set to true, multiple choices can be answered.
     *
     * This option cannot be enabled on enum questions.
     *
     * @return $this
     *
     * @throws \LogicException When multiselect is set to true on an enum question
     */
    public function setMultiselect(bool $multiselect): static
    {
        if ($this->usesEnum && $multiselect) {
            throw new \LogicException('Enums cannot be set as multiselect.');
        }

        $this->multiselect = $multiselect;
        $this->setValidator($this->getDefaultValidator());

        return $this;
    }

    /**
     * Returns whether the choices are multiselect.
     */
    public function isMultiselect(): bool
    {
        return $this->multiselect;
    }

    /**
     * Gets the prompt for choices.
     */
    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * Sets the prompt for choices.
     *
     * @return $this
     */
    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * Sets the error message for invalid values.
     *
     * The error message has a string placeholder (%s) for the invalid value.
     *
     * @return $this
     */
    public function setErrorMessage(string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        $this->setValidator($this->getDefaultValidator());

        return $this;
    }

    #[\Override]
    public function setMultiline(bool $multiline): static
    {
        if ($this->usesEnum && $multiline) {
            throw new \LogicException('Enums cannot be set as multiline.');
        }

        return parent::setMultiline($multiline);
    }

    /**
     * Sets a render function for the enum question.
     *
     * @param (callable(\UnitEnum):string)|null $customEnumRender
     *
     * @return $this
     */
    public function setCustomEnumRender(?callable $customEnumRender): static
    {
        $this->customEnumRender = null === $customEnumRender ? null : $customEnumRender(...);

        return $this;
    }

    /**
     * Gets the render function for the enum question.
     *
     * @return (callable(\UnitEnum):string)|null
     */
    public function getCustomEnumRender(): ?callable
    {
        return $this->customEnumRender;
    }

    private function getDefaultValidator(): callable
    {
        $choices = $this->choices;
        $errorMessage = $this->errorMessage;
        $multiselect = $this->multiselect;
        $isAssoc = $this->isAssoc($choices);

        return function ($selected) use ($choices, $errorMessage, $multiselect, $isAssoc) {
            if ($this->usesEnum) {
                if (\in_array($selected, $choices, true)) {
                    return $selected;
                }

                // Authorize a string representing the human-readable name of the enum (mainly for autocomplete)
                if (null !== $selected && null !== ($enumValue = $this->convertStringToEnumValue($selected, $choices[0]::class))) {
                    return $enumValue;
                }
            }

            if ($multiselect) {
                // Check for a separated comma values
                if (!preg_match('/^[^,]+(?:,[^,]+)*$/', (string) $selected, $matches)) {
                    throw new InvalidArgumentException(\sprintf($errorMessage, $selected));
                }

                $selectedChoices = explode(',', (string) $selected);
            } else {
                $selectedChoices = [$selected];
            }

            if ($this->isTrimmable()) {
                foreach ($selectedChoices as $k => $v) {
                    $selectedChoices[$k] = trim((string) $v);
                }
            }

            $multiselectChoices = [];
            foreach ($selectedChoices as $value) {
                $results = [];
                foreach ($choices as $key => $choice) {
                    if ($choice === $value) {
                        $results[] = $key;
                    }
                }

                if (\count($results) > 1) {
                    throw new InvalidArgumentException(\sprintf('The provided answer is ambiguous. Value should be one of "%s".', implode('" or "', $results)));
                }

                $result = array_search($value, $choices);

                if (!$isAssoc) {
                    if (false !== $result) {
                        $result = $choices[$result];
                    } elseif (isset($choices[$value])) {
                        $result = $choices[$value];
                    }
                } elseif (false === $result && isset($choices[$value])) {
                    $result = $value;
                }

                if (false === $result) {
                    throw new InvalidArgumentException(\sprintf($errorMessage, $value));
                }

                // For associative choices, consistently return the key as string:
                $multiselectChoices[] = $isAssoc ? (string) $result : $result;
            }

            if ($multiselect) {
                return $multiselectChoices;
            }

            return current($multiselectChoices);
        };
    }

    /**
     * @param class-string<\UnitEnum> $enumClass
     */
    private function convertStringToEnumValue(string $value, string $enumClass): ?\UnitEnum
    {
        if (!enum_exists($enumClass)) {
            return null;
        }

        if (null !== $this->customEnumRender) {
            $enumCases = $enumClass::cases();
            $renderFunction = $this->customEnumRender;

            return array_find(
                $enumCases,
                fn ($case) => $renderFunction($case) === $value
            );
        }

        $value = (string) s($value)->pascal();

        return array_find($enumClass::cases(), fn ($case) => $case->name === $value);
    }
}
