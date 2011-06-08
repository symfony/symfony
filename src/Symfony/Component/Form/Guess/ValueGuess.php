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
 * Contains a guessed value
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ValueGuess extends Guess
{
    /**
     * The guessed value
     * @var array
     */
    private $value;

    /**
     * Constructor
     *
     * @param string $value         The guessed value
     * @param integer $confidence   The confidence that the guessed class name
     *                              is correct
     */
    public function __construct($value, $confidence)
    {
        parent::__construct($confidence);

        $this->value = $value;
    }

    /**
     * Returns the guessed value
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
