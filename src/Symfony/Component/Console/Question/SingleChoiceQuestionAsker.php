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
 * Asks single choice questions.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class SingleChoiceQuestionAsker extends AbstractChoiceQuestionAsker
{
    protected $focusedChoiceTemplate = '<fg=cyan>❯</fg=cyan><fg=green>%s</fg=green>';

    /**
     * {@inheritdoc}
     */
    protected function getDefaultChoiceText($choiceText)
    {
        return $choiceText;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTextForUnfocusedChoiceValue($choiceValue)
    {
        return $this->choicesMap->getChoiceTextFromValue($choiceValue);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTextForFocusedChoiceValue($choiceValue)
    {
        return $this->choicesMap->getChoiceTextFromValue($choiceValue);
    }

    /**
     * {@inheritdoc}
     */
    protected function onEnterPress(ChoicesCursor $cursor)
    {
        return $this->choicesMap->getChoiceValueAt($cursor->getPosition());
    }
}
