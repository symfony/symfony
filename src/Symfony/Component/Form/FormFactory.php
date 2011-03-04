<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Config\FieldConfigInterface;
use Symfony\Component\Form\Config\Loader\ConfigLoaderInterface;
use Symfony\Component\Form\Guesser\FieldGuesserInterface;
use Symfony\Component\Form\Guesser\FieldGuess;

class FormFactory implements FormFactoryInterface
{
    private $configLoader;

    private $guessers = array();

    public function __construct(ConfigLoaderInterface $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    public function addGuesser(FieldGuesserInterface $guesser)
    {
        $this->guessers[] = $guesser;
    }

    public function getInstance($identifier, $key = null, array $options = array())
    {
        // TODO $identifier can be FQN of a config class

        $instance = null;
        $hierarchy = array();

        // TESTME
        if (null === $key) {
            $key = $identifier;
        }

        while (null !== $identifier) {
            // TODO check if identifier exists
            $config = $this->configLoader->getConfig($identifier);
            array_unshift($hierarchy, $config);
            $instance = $instance ?: $config->createInstance($key);
            $options = array_merge($config->getDefaultOptions($options), $options);
            $identifier = $config->getParent($options);
        }

        // TODO check if instance exists

        foreach ($hierarchy as $config) {
            $config->configure($instance, $options);
        }

        return $instance;
    }

    /**
     * @inheritDoc
     */
    public function getInstanceForProperty($class, $property, array $options = array())
    {
        // guess field class and options
        $identifierGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessIdentifier($class, $property);
        });

        if (!$identifierGuess) {
            throw new \RuntimeException(sprintf('No field could be guessed for property "%s" of class %s', $property, $class));
        }

        // guess maximum length
        $maxLengthGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessMaxLength($class, $property);
        });

        // guess whether field is required
        $requiredGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessRequired($class, $property);
        });

        // construct field
        $identifier = $identifierGuess->getIdentifier();

        // TODO all configs supporting the option "max_length" should receive
        // it
        if ($maxLengthGuess && $identifier == 'text') {
// TODO enable me again later
//            $options = array_merge(array('max_length' => $maxLengthGuess->getValue()), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        $options = array_merge($identifierGuess->getOptions(), $options);

        return $this->getInstance($identifier, $property, $options);
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

        return FieldGuess::getBestGuess($guesses);
    }
}