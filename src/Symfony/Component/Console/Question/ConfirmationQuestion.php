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
    public const DEFAULT_TRUE_ANSWER_REGEX = '/^y/i';
    public const DEFAULT_TRUE_ANSWER_TEXT = 'yes';
    public const DEFAULT_FALSE_ANSWER_TEXT = 'no';

    private string $trueAnswerRegex;

    private string $trueAnswerText;

    private string $falseAnswerText;

    public function __construct(string $question, bool $default = true, string $trueAnswerRegex = self::DEFAULT_TRUE_ANSWER_REGEX, string $trueAnswerText = self::DEFAULT_TRUE_ANSWER_TEXT, string $falseAnswerText = self::DEFAULT_FALSE_ANSWER_TEXT)
    {
        parent::__construct($question, $default);

        $this->trueAnswerRegex = $trueAnswerRegex;
        $this->trueAnswerText = $trueAnswerText;
        $this->falseAnswerText = $falseAnswerText;
        $this->setNormalizer($this->getDefaultNormalizer());
    }

    public function getTrueAnswerText(): string
    {
        return $this->trueAnswerText;
    }

    public function getFalseAnswerText(): string
    {
        return $this->falseAnswerText;
    }

    /**
     * Returns the default answer normalizer.
     */
    private function getDefaultNormalizer(): callable
    {
        $default = $this->getDefault();
        $regex = $this->trueAnswerRegex;

        return function ($answer) use ($default, $regex) {
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
