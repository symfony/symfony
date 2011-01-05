<?php

namespace Symfony\Component\Form\FieldFactory;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Contains a guessed class name and a list of options for creating an instance
 * of that class
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FieldFactoryClassGuess extends FieldFactoryGuess
{
    /**
     * The guessed options for creating an instance of the guessed class
     * @var array
     */
    protected $options;

    /**
     * Constructor
     *
     * @param string $class         The guessed class name
     * @param array  $options       The options for creating instances of the
     *                              guessed class
     * @param integer $confidence   The confidence that the guessed class name
     *                              is correct
     */
    public function __construct($class, array $options, $confidence)
    {
        parent::__construct($class, $confidence);

        $this->options = $options;
    }

    /**
     * Returns the guessed class name
     *
     * @return string
     */
    public function getClass()
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