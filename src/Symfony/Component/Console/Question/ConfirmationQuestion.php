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
     * Constructor.
     *
     * @param string $question       The question to ask to the user
     * @param bool   $default        The default answer to return, true or false
     * @param array  $correctAnswers All the answers equivalent to "yes" (only first letter will be taken into account)
     */
    public function __construct($question, $default = true, array $correctAnswers = array('y'))
    {
        parent::__construct($question, (bool) $default);

        $correctAnswers = array_map(function ($value) {
                return strtolower($value[0]);
            }, $correctAnswers);

        $this->setNormalizer($this->getDefaultNormalizer($correctAnswers));
    }

    /**
     * Returns the default answer normalizer.
     *
     * @param array $correctAnswers
     *
     * @return callable
     */
    private function getDefaultNormalizer(array $correctAnswers)
    {
        $default = $this->getDefault();

        return function ($answer) use ($default, $correctAnswers) {
            if (is_bool($answer)) {
                return $answer;
            }

            if (false === $default) {
                return $answer && in_array(strtolower($answer[0]), $correctAnswers);
            }

            return !$answer || in_array(strtolower($answer[0]), $correctAnswers);
        };
    }
}
