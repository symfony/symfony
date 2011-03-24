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

use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\Form\Type\Loader\TypeLoaderInterface;
use Symfony\Component\Form\Type\Guesser\TypeGuesserInterface;
use Symfony\Component\Form\Type\Guesser\Guess;

class FormFactory implements FormFactoryInterface
{
    private $typeLoader;

    private $guessers = array();

    public function __construct(TypeLoaderInterface $typeLoader)
    {
        $this->typeLoader = $typeLoader;
    }

    public function addGuesser(TypeGuesserInterface $guesser)
    {
        $this->guessers[] = $guesser;
    }

    public function createBuilder($type, $name = null, array $options = array())
    {
        // TODO $type can be FQN of a type class

        $builder = null;
        $types = array();

        // TESTME
        if (null === $name && preg_match('/\w+$/', $type, $matches)) {
            $name = $matches[0];
        }

        while (null !== $type) {
            // TODO check if type exists
            $type = $this->typeLoader->getType($type);
            array_unshift($types, $type);
            $options = array_merge($type->getDefaultOptions($options), $options);
            $builder = $builder ?: $type->createBuilder($options);
            $type = $type->getParent($options);
        }

        // TODO check if instance exists

        $builder->setName($name);
        $builder->setTypes($types);
        $builder->setFormFactory($this);

        foreach ($types as $type) {
            $type->configure($builder, $options);
        }

        return $builder;
    }

    public function create($type, $name = null, array $options = array())
    {
        return $this->createBuilder($type, $name, $options)->getForm();
    }

    public function createBuilderForProperty($class, $property, array $options = array())
    {
        // guess field class and options
        $typeGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessType($class, $property);
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
        $type = $typeGuess ? $typeGuess->getType() : 'text';

        if ($maxLengthGuess) {
            $options = array_merge(array('max_length' => $maxLengthGuess->getValue()), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        if ($typeGuess) {
            $options = array_merge($typeGuess->getOptions(), $options);
        }

        return $this->createBuilder($type, $property, $options);
    }

    /**
     * @inheritDoc
     */
    public function createForProperty($class, $property, array $options = array())
    {
        return $this->createBuilderForProperty($class, $property, $options)->getForm();
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

        return Guess::getBestGuess($guesses);
    }
}