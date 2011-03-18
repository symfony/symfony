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
use Symfony\Component\Form\FieldGuesser\FieldGuesserInterface;
use Symfony\Component\Form\FieldGuesser\FieldGuess;

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

    public function createBuilder($identifier, $name = null, array $options = array())
    {
        // TODO $identifier can be FQN of a config class

        $builder = null;
        $hierarchy = array();

        // TESTME
        if (null === $name) {
            $name = $identifier;
        }

        while (null !== $identifier) {
            // TODO check if identifier exists
            $config = $this->configLoader->getConfig($identifier);
            array_unshift($hierarchy, $config);
            $options = array_merge($config->getDefaultOptions($options), $options);
            $builder = $builder ?: $config->createBuilder($options);
            $identifier = $config->getParent($options);
        }

        // TODO check if instance exists

        $builder->setName($name);
        $builder->setFormFactory($this);

        foreach ($hierarchy as $config) {
            $config->configure($builder, $options);
        }

        return $builder;
    }

    public function create($identifier, $name = null, array $options = array())
    {
        return $this->createBuilder($identifier, $name, $options)->getInstance();
    }

    public function createBuilderForProperty($class, $property, array $options = array())
    {
        // guess field class and options
        $identifierGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessIdentifier($class, $property);
        });

        // guess maximum length
        $maxLengthGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessMaxLength($class, $property);
        });

        // guess whether field is required
        $requiredGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessRequired($class, $property);
        });

        // construct field
        $identifier = $identifierGuess ? $identifierGuess->getIdentifier() : 'text';

        if ($maxLengthGuess) {
            $options = array_merge(array('max_length' => $maxLengthGuess->getValue()), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        if ($identifierGuess) {
            $options = array_merge($identifierGuess->getOptions(), $options);
        }

        return $this->createBuilder($identifier, $property, $options);
    }

    /**
     * @inheritDoc
     */
    public function createForProperty($class, $property, array $options = array())
    {
        return $this->createBuilderForProperty($class, $property, $options)->getInstance();
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