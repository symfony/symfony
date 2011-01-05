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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Default implementation of FieldFactoryInterface
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @see FieldFactoryInterface
 */
class FieldFactory implements FieldFactoryInterface
{
    /**
     * A list of guessers for guessing field classes and options
     * @var array
     */
    protected $guessers;

    /**
     * Constructor
     *
     * @param array $guessers  A list of instances implementing
     *                         FieldFactoryGuesserInterface
     */
    public function __construct(array $guessers)
    {
        foreach ($guessers as $guesser) {
            if (!$guesser instanceof FieldFactoryGuesserInterface) {
                throw new UnexpectedTypeException($guesser, 'FieldFactoryGuesserInterface');
            }
        }
        $this->guessers = $guessers;
    }

    /**
     * @inheritDoc
     */
    public function getInstance($object, $property, array $options = array())
    {
        $guess = $this->guess(function ($guesser) use ($object, $property) {
            return $guesser->guessMaxLength($object, $property);
        });

        $maxLength = $guess ? $guess->getValue() : null;

        $guess = $this->guess(function ($guesser) use ($object, $property) {
            return $guesser->guessClass($object, $property);
        });

        if (!$guess) {
            throw new \RuntimeException(sprintf('No field could be guessed for property "%s" of class %s', $property, get_class($object)));
        }

        $class = $guess->getClass();
        $textField = 'Symfony\Component\Form\TextField';

        if (null !== $maxLength && ($class == $textField || is_subclass_of($class, $textField))) {
            $options = array_merge(array('max_length' => $maxLength), $options);
        }

        $options = array_merge($guess->getOptions(), $options);
        $field = new $class($property, $options);

        $guess = $this->guess(function ($guesser) use ($object, $property) {
            return $guesser->guessRequired($object, $property);
        });

        if ($guess) {
            $field->setRequired($guess->getValue());
        }

        return $field;
    }

    /**
     * Executes a closure for each guesser and returns the best guess from the
     * return values
     *
     * @param  \Closure $closure  The closure to execute. Accepts a guesser as
     *                            argument and should return a FieldFactoryGuess
     *                            instance
     * @return FieldFactoryGuess  The guess with the highest confidence
     */
    protected function guess(\Closure $closure)
    {
        $guesses = array();

        foreach ($this->guessers as $guesser) {
            if ($guess = $closure($guesser)) {
                $guesses[] = $guess;
            }
        }

        return FieldFactoryGuess::getBestGuess($guesses);
    }
}