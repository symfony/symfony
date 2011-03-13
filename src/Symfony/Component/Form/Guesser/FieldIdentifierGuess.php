<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Guesser;

/**
 * Contains a guessed class name and a list of options for creating an instance
 * of that class
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FieldIdentifierGuess extends FieldGuess
{
    /**
     * The guessed options for creating an instance of the guessed class
     * @var array
     */
    protected $options;

    /**
     * Constructor
     *
     * @param string $identifier    The guessed field identifier
     * @param array  $options       The options for creating instances of the
     *                              guessed class
     * @param integer $confidence   The confidence that the guessed class name
     *                              is correct
     */
    public function __construct($identifier, array $options, $confidence)
    {
        parent::__construct($identifier, $confidence);

        $this->options = $options;
    }

    /**
     * Returns the guessed class name
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->getValue();
    }

    /**
     * Returns the guessed options for creating instances of the guessed class
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}