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
 * Represents a yes/no question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConfirmationQuestion extends Question
{
    /**
     * @param string $question        The question to ask to the user
     * @param bool   $default         The default answer to return, true or false
     * @param string $trueAnswerRegex A regex to match the "yes" answer
     * @param string $trueLabel       The displayed label for the affirmative answer
     * @param string $falseLabel      The displayed label for the negative answer
     */
    public function __construct(
        string $question,
        bool $default = true,
        private string $trueAnswerRegex = '/^y/i',
        private string $trueLabel = 'yes',
        private string $falseLabel = 'no',
    ) {
        parent::__construct($question, $default);

        $this->setNormalizer($this->getDefaultNormalizer());
    }

    public function getTrueLabel(): string
    {
        return $this->trueLabel;
    }

    public function getFalseLabel(): string
    {
        return $this->falseLabel;
    }

    /**
     * Returns the default answer normalizer.
     */
    private function getDefaultNormalizer(): callable
    {
        $default = $this->getDefault();
        $regex = $this->trueAnswerRegex;

        return static function ($answer) use ($default, $regex) {
            if (\is_bool($answer)) {
                return $answer;
            }

            $answerIsTrue = (bool) preg_match($regex, $answer);
            if (false === $default) {
                return $answer && $answerIsTrue;
            }

            return '' === $answer || $answerIsTrue;
        };
    }
}
