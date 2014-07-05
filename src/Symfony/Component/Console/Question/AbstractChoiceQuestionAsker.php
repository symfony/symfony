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

use Symfony\Component\Console\EscapeSequences;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Keyboard;

/**
 * Base class for choice question askers.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
abstract class AbstractChoiceQuestionAsker implements QuestionAskerInterface
{
    protected $focusedChoiceTemplate = '%s';
    protected $unfocusedChoiceTemplate = ' %s';
    protected $question;
    protected $choicesMap;

    public function __construct(ChoiceQuestion $question)
    {
        $this->question = $question;
        $this->choicesMap = new ChoicesMap($question->getChoices());

        if (null !== $focusedChoiceTemplate = $question->getOption('focused_choice_template')) {
            $this->focusedChoiceTemplate = $focusedChoiceTemplate;
        }

        if (null !== $unfocusedChoiceTemplate = $question->getOption('unfocused_choice_template')) {
            $this->unfocusedChoiceTemplate = $unfocusedChoiceTemplate;
        }
    }

    /**
     * Asks the question.
     *
     * @param OutputInterface $output
     * @param resource        $inputStream
     *
     * @return mixed The answer
     */
    public function ask(OutputInterface $output, $inputStream)
    {
        $output->writeln($this->question->getQuestion());

        $choiceLines = array();
        foreach ($this->question->getChoices() as $choice) {
            $choiceLines[] = sprintf($this->unfocusedChoiceTemplate, $this->getDefaultChoiceText($choice));
        }

        $output->write(implode("\n", $choiceLines));

        $cursor = new ChoicesCursor(count($this->question->getChoices()));

        return $this->doAsk($output, $cursor, $inputStream);
    }

    /**
     * Gets the default text to display for a choice text.
     *
     * @param string $choiceText
     *
     * @return string
     */
    abstract protected function getDefaultChoiceText($choiceText);

    /**
     * Gets the text to display for an unfocused choice value.
     *
     * @param mixed $choiceValue
     *
     * @return string
     */
    abstract protected function getTextForUnfocusedChoiceValue($choiceValue);

    /**
     * Gets the text to display for a focused choice value.
     *
     * @param mixed $choiceValue
     *
     * @return string
     */
    abstract protected function getTextForFocusedChoiceValue($choiceValue);

    /**
     * Hooks when the enter key is pressed.
     *
     * @param ChoicesCursor $cursor
     *
     * @return string|int The answer of the question
     */
    abstract protected function onEnterPress(ChoicesCursor $cursor);

    /**
     * Hooks when the spacebar key is pressed.
     *
     * @param OutputInterface $output
     * @param ChoicesCursor   $cursor
     */
    protected function onSpacebarPress(OutputInterface $output, ChoicesCursor $cursor)
    {
    }

    /**
     * Replaces the current line in the output by the given text.
     *
     * @param string          $text
     * @param OutputInterface $output
     */
    protected function replaceln($text, OutputInterface $output)
    {
        $output->write(EscapeSequences::LINE_ERASE);
        $output->write(sprintf(EscapeSequences::CURSOR_MOVE_BACKWARD_N, 10000));
        $output->write($text);
        $output->write(sprintf(EscapeSequences::CURSOR_MOVE_BACKWARD_N, 10000));
    }

    private function doAsk(OutputInterface $output, ChoicesCursor $cursor, $inputStream)
    {
        $countChoices = count($this->question->getChoices());
        $result = null;

        $output->write(EscapeSequences::CURSOR_HIDE);

        while (!feof($inputStream)) {
            $pressedKey = Keyboard::getPressedKey($inputStream);

            switch ($pressedKey) {
                case Keyboard::KEY_UP_ARROW:
                    $this->moveCursor($output, $cursor, true);
                    break;

                case Keyboard::KEY_DOWN_ARROW:
                    $this->moveCursor($output, $cursor, false);
                    break;

                case Keyboard::KEY_SPACEBAR:
                    $this->onSpacebarPress($output, $cursor);
                    break;

                case Keyboard::KEY_ENTER:
                    $result = $this->onEnterPress($cursor);
                    break 2;
            }
        }

        if (0 === strlen($result) || !$cursor->hasMoved()) {
            $result = $this->question->getDefault();
        }

        // Move the cursor at the last line
        $output->write(sprintf(EscapeSequences::CURSOR_MOVE_DOWN_N, $countChoices - $cursor->getPosition()));

        $output->write(EscapeSequences::CURSOR_SHOW);

        return $result;
    }

    /**
     * Moves the cursor in the given direction.
     *
     * @param OutputInterface $output    The output
     * @param ChoicesCursor   $cursor    The cursor
     * @param bool            $direction True to move up, false to move down
     */
    private function moveCursor(OutputInterface $output, ChoicesCursor $cursor, $direction)
    {
        $currentPosition = $cursor->getPosition();

        if ($direction) {
            $positionDiff = $cursor->moveUp();
        } else {
            $positionDiff = $cursor->moveDown();
        }

        $newPosition = $cursor->getPosition();

        $this->focusChoice($output, $currentPosition, $newPosition, $positionDiff);
    }

    /**
     * Focuses the choice.
     *
     * @param OutputInterface $output          The output
     * @param int             $currentPosition The current cursor position
     * @param int             $choicePosition  The position of the choice to focus
     * @param int             $positionDiff    The difference between the two positions
     */
    private function focusChoice(OutputInterface $output, $currentPosition, $choicePosition, $positionDiff)
    {
        // Unfocus the current choice
        $currentChoiceValue = $this->choicesMap->getChoiceValueAt($currentPosition);
        $this->replaceln(
            sprintf($this->unfocusedChoiceTemplate, $this->getTextForUnfocusedChoiceValue($currentChoiceValue)),
            $output
        );

        if ($positionDiff > 0) {
            $output->write(sprintf(EscapeSequences::CURSOR_MOVE_UP_N, $positionDiff));
        } else {
            $output->write(sprintf(EscapeSequences::CURSOR_MOVE_DOWN_N, -$positionDiff));
        }

        // Focus the choice
        $choiceValue = $this->choicesMap->getChoiceValueAt($choicePosition);
        $choiceText = $this->getTextForFocusedChoiceValue($choiceValue);
        $this->replaceln(sprintf($this->focusedChoiceTemplate, $choiceText), $output);
    }
}
