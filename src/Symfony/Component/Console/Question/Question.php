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
 * Represents a Question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Question
{
    private $question;
    private $attempts = false;
    private $hidden = false;
    private $hiddenFallback = true;
    private $autocompleter;
    private $validator;
    private $default;
    private $normalizer;

    /**
     * Constructor.
     *
     * @param string $question The question to ask to the user
     * @param mixed  $default  The default answer to return if the user enters nothing
     */
    public function __construct($question, $default = null)
    {
        $this->question = $question;
        $this->default = $default;
    }

    public function getQuestion()
    {
        return $this->question;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function isHidden()
    {
        return $this->hidden;
    }

    public function setHidden($hidden)
    {
        if ($this->autocompleter) {
            throw new \LogicException('A hidden question cannot use the autocompleter.');
        }

        $this->hidden = (Boolean) $hidden;
    }

    public function isHiddenFallback()
    {
        return $this->fallback;
    }

    /**
     * Sets whether to fallback on non-hidden question if the response can not be hidden.
     */
    public function setHiddenFallback($fallback)
    {
        $this->fallback = (Boolean) $fallback;
    }

    public function getAutocompleter()
    {
        return $this->autocompleter;
    }

    public function setAutocompleter($autocompleter)
    {
        if ($this->hidden) {
            throw new \LogicException('A hidden question cannot use the autocompleter.');
        }

        $this->autocompleter = $autocompleter;
    }

    public function setValidator($validator)
    {
        $this->validator = $validator;
    }

    public function getValidator()
    {
        return $this->validator;
    }

    public function setMaxAttemps($attempts)
    {
        $this->attempts = $attempts;
    }

    public function getMaxAttemps()
    {
        return $this->attempts;
    }

    public function setNormalizer($normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function getNormalizer()
    {
        return $this->normalizer;
    }
}
