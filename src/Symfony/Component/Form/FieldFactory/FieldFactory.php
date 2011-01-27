<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldFactory;

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
        // guess field class and options
        $classGuess = $this->guess(function ($guesser) use ($object, $property) {
            return $guesser->guessClass($object, $property);
        });

        if (!$classGuess) {
            throw new \RuntimeException(sprintf('No field could be guessed for property "%s" of class %s', $property, get_class($object)));
        }

        // guess maximum length
        $maxLengthGuess = $this->guess(function ($guesser) use ($object, $property) {
            return $guesser->guessMaxLength($object, $property);
        });

        // guess whether field is required
        $requiredGuess = $this->guess(function ($guesser) use ($object, $property) {
            return $guesser->guessRequired($object, $property);
        });

        // construct field
        $class = $classGuess->getClass();
        $textField = 'Symfony\Component\Form\TextField';

        if ($maxLengthGuess && ($class == $textField || is_subclass_of($class, $textField))) {
            $options = array_merge(array('max_length' => $maxLengthGuess->getValue()), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        $options = array_merge($classGuess->getOptions(), $options);

        return new $class($property, $options);
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