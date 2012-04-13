<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Guess;

/**
 * Contains a guessed pattern
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Julien Galenski <julien.galenski@gmail.com>
 */
class PatternGuess extends Guess
{
    /**
     * The guessed value
     * @var string
     */
    private $pattern;

    /**
     * Constructor
     *
     * @param string $value         The guessed value
     * @param integer $confidence   The confidence that the guessed class name
     *                              is correct
     */
    public function __construct($pattern, $confidence)
    {
        parent::__construct($confidence);

        $this->pattern = $pattern;
    }

    /**
     * Returns the guessed pattern
     *
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }
}
