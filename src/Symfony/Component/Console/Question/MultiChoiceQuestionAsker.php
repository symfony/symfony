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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Asks multi choice questions.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class MultiChoiceQuestionAsker extends AbstractChoiceQuestionAsker
{
    protected $focusedChoiceTemplate = '<fg=cyan>❯</fg=cyan>%s';
    private $selectedChoiceTemplate = '<fg=green>⬢</fg=green> %s';
    private $deselectedChoiceTemplate = '⬡ %s';
    private $selectedChoices = array();

    public function __construct(ChoiceQuestion $question)
    {
        parent::__construct($question);

        if (null !== $selectedChoiceTemplate = $question->getDisplayOption('selected_choice_template')) {
            $this->selectedChoiceTemplate = $selectedChoiceTemplate;
        }

        if (null !== $deselectedChoiceTemplate = $question->getDisplayOption('deselected_choice_template')) {
            $this->deselectedChoiceTemplate = $deselectedChoiceTemplate;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultChoiceText($choice)
    {
        return sprintf($this->deselectedChoiceTemplate, $choice);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTextForUnfocusedChoiceValue($choiceValue)
    {
        return $this->getTextForChoiceValue($choiceValue);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTextForFocusedChoiceValue($choiceValue)
    {
        return $this->getTextForChoiceValue($choiceValue);
    }

    /**
     * {@inheritdoc}
     */
    protected function onEnterPress(ChoicesCursor $cursor)
    {
        return implode(',', array_keys($this->selectedChoices));
    }

    /**
     * {@inheritdoc}
     */
    protected function onSpacebarPress(OutputInterface $output, ChoicesCursor $cursor)
    {
        $choiceValue = $this->choicesMap->getChoiceValueAt($cursor->getPosition());
        $choiceText = $this->choicesMap->getChoiceTextAt($cursor->getPosition());

        if (isset($this->selectedChoices[$choiceValue])) {
            $this->focusChoiceText($output, sprintf($this->deselectedChoiceTemplate, $choiceText));
            unset($this->selectedChoices[$choiceValue]);
        } else {
            $this->focusChoiceText($output, sprintf($this->selectedChoiceTemplate, $choiceText));
            $this->selectedChoices[$choiceValue] = true;
        }
    }

    private function focusChoiceText(OutputInterface $output, $choiceText)
    {
        $this->replaceln(sprintf($this->focusedChoiceTemplate, $choiceText), $output);
    }

    private function getTextForChoiceValue($choiceValue)
    {
        $choiceText = $this->choicesMap->getChoiceTextFromValue($choiceValue);

        if (isset($this->selectedChoices[$choiceValue])) {
            return sprintf($this->selectedChoiceTemplate, $choiceText);
        }

        return sprintf($this->deselectedChoiceTemplate, $choiceText);
    }
}
