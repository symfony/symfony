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
     * @param string $question     The question to ask to the user
     * @param bool   $default      The default answer to return, true or false
     * @param array  $rightAnswers All the answers equivalent to "yes" (only first letter will be taken into account)
     */
    public function __construct($question, $default = true, $rightAnswers = ['y'])
    {
        parent::__construct($question, (bool) $default);

        $rightAnswers = array_map(function ($value) {
                return strtolower($value[0]);
            }, $rightAnswers);

        $this->setNormalizer($this->getDefaultNormalizer($rightAnswers));
    }

    /**
     * Returns the default answer normalizer.
     *
     * @param array $rightAnswers
     *
     * @return callable
     */
    private function getDefaultNormalizer(array $rightAnswers)
    {
        $default = $this->getDefault();

        return function ($answer) use ($default, $rightAnswers) {
            if (is_bool($answer)) {
                return $answer;
            }

            if (false === $default) {
                return $answer && in_array(strtolower($answer[0]), $rightAnswers);
            }

            return !$answer || in_array(strtolower($answer[0]), $rightAnswers);
        };
    }
}
