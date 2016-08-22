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
 * Represents a repeated Question.
 *
 * @author Guilhem N. <egetick@gmail.com>
 */
final class RepeatedQuestion extends Question
{
    private $generator;

    public function __construct($question, \Generator $generator = null)
    {
        parent::__construct($question);
        if (null === $generator) {
            $generator = call_user_func(function () {
                do {
                    $answer = yield;
                } while (null !== $answer);
            });
        }
        $this->generator = $generator;
    }

    /**
     * Returns the question generator.
     *
     * @return \Generator
     */
    public function getGenerator()
    {
        return $this->generator;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefault()
    {
        return $this->generator->current();
    }
}
